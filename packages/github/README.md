# waaseyaa/github

**Layer 3 — Services**

GitHub API client for issues, milestones, and pull requests.

`GitHubClient` wraps the REST v3 endpoints used by repo automation tooling: list issues, create/edit milestones, comment on PRs. `Issue`, `Milestone`, and `PullRequest` are typed value objects rather than raw arrays. Authentication is via personal access token or GitHub App installation token passed at construction.

Key classes: `GitHubClient`, `Issue`, `Milestone`, `PullRequest`, `GitHubException`.
