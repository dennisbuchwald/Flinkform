#!/usr/bin/env bash
#
# Run every standalone test in this folder. Exits non-zero if any fails.
#
# The browser smoke tests (module-smoke.html, deferred-smoke.html) are not
# part of this: they need a browser and a local web server, see their
# headers. Run them by hand after every `npm run build`.
#
# Usage:  tests/run.sh

set -u
cd "$(dirname "$0")/.." || exit 1

failed=0
total=0

for t in tests/*-test.php; do
    total=$((total + 1))
    out=$(php "$t" 2>&1)
    if [ $? -ne 0 ]; then
        failed=$((failed + 1))
        echo "FAIL  $t"
        echo "$out" | grep -E '^FAIL|FAILED|Fatal' | sed 's/^/      /'
    else
        echo "ok    $t  ($(echo "$out" | tail -1))"
    fi
done

for t in tests/*.mjs; do
    total=$((total + 1))
    out=$(node "$t" 2>/dev/null)
    if [ $? -ne 0 ]; then
        failed=$((failed + 1))
        echo "FAIL  $t"
        echo "$out" | grep -E '^FAIL|FAILED' | sed 's/^/      /'
    else
        echo "ok    $t  ($(echo "$out" | tail -1))"
    fi
done

echo
if [ "$failed" -gt 0 ]; then
    echo "$failed of $total test files FAILED."
    exit 1
fi
echo "All $total test files passed."
