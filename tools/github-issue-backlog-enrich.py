#!/usr/bin/env python3
"""Enrich open GitHub issues with timeline merged-PR signals and cluster tags.

Reads docs/audits/github-issues-open-snapshot.json (from gh issue list --json).
Writes docs/audits/github-issues-triage-enriched.csv and .md

Usage: from repo root, with gh authenticated:
  python3 tools/github-issue-backlog-enrich.py
"""
from __future__ import annotations

import csv
import json
import re
import subprocess
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
SNAPSHOT = REPO_ROOT / "docs/audits/github-issues-open-snapshot.json"
OUT_CSV = REPO_ROOT / "docs/audits/github-issues-triage-enriched.csv"
OUT_MD = REPO_ROOT / "docs/audits/github-issues-triage-enriched.md"

GQL = """
query($owner: String!, $name: String!, $n: Int!) {
  repository(owner: $owner, name: $name) {
    issue(number: $n) {
      number
      title
      state
      timelineItems(first: 80) {
        nodes {
          __typename
          ... on CrossReferencedEvent {
            source {
              ... on PullRequest {
                number
                title
                state
                merged
              }
            }
          }
          ... on ClosedEvent {
            stateReason
          }
        }
      }
    }
  }
}
"""


def cluster_title(title: str) -> str:
    t = title.lower()
    if "impl(remediation)" in t or ("remediation" in t and "impl(" in t):
        return "remediation_mseries"
    if "phpdoc" in t or "@covers" in title:
        return "phpdoc_covers"
    if re.search(r"audit\((contracts|testing)\)", title, re.I):
        return "audit_contracts_testing"
    if title.startswith("refactor: extract interfaces"):
        return "refactor_extract_interfaces"
    if "layer gate" in t or "layer gate:" in t:
        return "layer_gate"
    if "epic" in t and ("—" in title or "-" in title[:20]):
        return "epic"
    if "inertia" in t or "ssr boundary" in t:
        return "inertia_ssr"
    if "bimaaji" in t or "telescope" in t and "agent" in t:
        return "bimaaji_telemetry"
    if "m11" in t or "governed-change" in t:
        return "governance_m11"
    if "rotate" in t or "split_github" in t or "split token" in t:
        return "ops_tokens"
    if "northcloud" in t or "adr-004" in t:
        return "ecosystem_rollout"
    if title.startswith("chore: triage"):
        return "chore_triage_final"
    if "symfony" in t and "wrap" in t:
        return "symfony_wrap"
    if "[layer" in t or "layer " in t[:30].lower():
        return "layer_tagged"
    return "other"


def graphql_issue(num: int) -> dict:
    payload = {
        "query": GQL,
        "variables": {"owner": "waaseyaa", "name": "framework", "n": num},
    }
    raw = subprocess.check_output(
        ["gh", "api", "graphql", "--input", "-"],
        input=json.dumps(payload),
        text=True,
    )
    return json.loads(raw)


def parse_timeline(data: dict) -> tuple[list[str], bool, str]:
    """Returns (merged_pr_summaries, has_closed_event, last_close_reason)."""
    merged: list[str] = []
    closed_ev = False
    close_reason = ""
    repo = data.get("data", {}).get("repository")
    if not repo or not repo.get("issue"):
        return merged, closed_ev, close_reason
    issue = repo["issue"]
    if issue.get("state") != "OPEN":
        # Should not happen for our snapshot
        pass
    for node in issue.get("timelineItems", {}).get("nodes") or []:
        t = node.get("__typename")
        if t == "CrossReferencedEvent":
            src = node.get("source") or {}
            if src.get("merged") is True:
                merged.append(f"PR#{src.get('number')}: {src.get('title', '')[:60]}")
        elif t == "ClosedEvent":
            closed_ev = True
            close_reason = str(node.get("stateReason") or "")
    return merged, closed_ev, close_reason


def main() -> int:
    if not SNAPSHOT.exists():
        print(f"Missing {SNAPSHOT}", file=sys.stderr)
        return 1
    issues = json.loads(SNAPSHOT.read_text())
    rows: list[dict] = []

    for i, issue in enumerate(issues):
        num = issue["number"]
        title = issue["title"]
        ms = (issue.get("milestone") or {}) or {}
        milestone = ms.get("title", "")
        cluster = cluster_title(title)

        try:
            gql = graphql_issue(num)
        except subprocess.CalledProcessError as e:
            rows.append(
                {
                    "number": num,
                    "title": title[:120],
                    "milestone": milestone,
                    "cluster": cluster,
                    "merged_prs": "",
                    "graphql_error": str(e),
                    "suggest": "manual",
                }
            )
            continue

        merged, _ce, _cr = parse_timeline(gql)
        merged_str = " | ".join(merged[:5])
        # Open issues should not have ClosedEvent in future from reopen - still OPEN
        suggest = "verify_ac"
        if merged and (
            "feat(#" in merged_str or "fix(#" in merged_str or "test(#" in merged_str
        ):
            suggest = "verify_ac"  # linked merged PRs — need body AC vs code
        rows.append(
            {
                "number": num,
                "title": title[:200],
                "milestone": milestone,
                "cluster": cluster,
                "merged_prs": merged_str,
                "graphql_error": "",
                "suggest": suggest,
            }
        )
        if (i + 1) % 20 == 0:
            print(f"Processed {i + 1}/{len(issues)}", file=sys.stderr)

    OUT_CSV.parent.mkdir(parents=True, exist_ok=True)
    with OUT_CSV.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()) if rows else [])
        w.writeheader()
        w.writerows(rows)

    # Markdown summary by cluster
    from collections import Counter

    by_c = Counter(r["cluster"] for r in rows)
    lines = [
        "# GitHub open issues — triage export (enriched)",
        "",
        "Generated by `tools/github-issue-backlog-enrich.py`.",
        "",
        "Source: [github-issues-open-snapshot.json](github-issues-open-snapshot.json).",
        "",
        "## Cluster counts",
        "",
        "| Cluster | Count |",
        "|---------|------:|",
    ]
    for k, v in by_c.most_common():
        lines.append(f"| {k} | {v} |")
    lines.extend(
        [
            "",
            "## Full table (CSV)",
            "",
            f"See [github-issues-triage-enriched.csv](github-issues-triage-enriched.csv) for `number`, `cluster`, `merged_prs`, `suggest`.",
            "",
        ]
    )
    OUT_MD.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"Wrote {OUT_CSV} and {OUT_MD} ({len(rows)} rows)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
