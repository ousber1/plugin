#!/usr/bin/env bash
# Multi-process race tests. Usage: WP="php wp-cli.phar --path=/path/to/wp --allow-root" ./run.sh
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
WP=${WP:-wp}
N=${N:-8}
SETUP=$($WP eval-file "$DIR/setup.php" 2>/dev/null | tail -1)
echo "setup: $SETUP"
export RSP_CONC="$SETUP"
for op in redeem pay reward; do
  tmp=$(mktemp -d)
  for i in $(seq 1 "$N"); do RSP_CONC_OP=$op $WP eval-file "$DIR/worker.php" >"$tmp/$i" 2>/dev/null & done
  wait
  echo "$op: $(cat "$tmp"/* | sort | uniq -c | tr '\n' ' ')"
  rm -rf "$tmp"
done
RES=$($WP eval-file "$DIR/verify.php" 2>/dev/null | tail -1)
echo "verify: $RES"
python3 - "$RES" <<'PY'
import json,sys
r=json.loads(sys.argv[1])
checks={
 "gift redeemed exactly once": r["gift_status"]=="redeemed" and r["gift_jobs"]==1 and r["recipient_accounts"]==1,
 "concurrent payment events -> one job, one account": r["order_jobs"]==1 and r["order_accounts"]==1,
 "concurrent reward runs -> one 30-day credit": r["referrer_balance"]==30 and r["referrer_ledger"]==1,
}
ok=True
for k,v in checks.items():
    print(("PASS " if v else "FAIL ")+k); ok=ok and v
sys.exit(0 if ok else 1)
PY
