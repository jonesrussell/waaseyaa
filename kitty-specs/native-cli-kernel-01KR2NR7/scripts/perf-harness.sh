#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

CMD="${1:-list}"
RUNS="${2:-10}"

timings=()
mems=()
for _ in $(seq 1 "$RUNS"); do
  out=$(/usr/bin/env time -f "TIME=%e MEM=%M" \
        php -d opcache.enable_cli=0 bin/waaseyaa "$CMD" 2>&1 1>/dev/null \
        | tail -n1)
  timings+=("$(awk '{print $1}' <<<"$out" | cut -d= -f2)")
  mems+=("$(awk '{print $2}' <<<"$out" | cut -d= -f2)")
done

# Sort numerically and emit median + max
median() { printf '%s\n' "$@" | sort -n | awk 'BEGIN{c=0}{a[c++]=$1}END{print a[int(c/2)]}'; }
max()    { printf '%s\n' "$@" | sort -n | tail -n1; }

echo "wall_median_s=$(median "${timings[@]}")"
echo "mem_max_kb=$(max "${mems[@]}")"
