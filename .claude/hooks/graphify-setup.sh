#!/usr/bin/env bash
# SessionStart: make sure the graphify CLI is installed so the skill and hooks work.
command -v graphify >/dev/null 2>&1 && exit 0
pip install -q graphifyy >/dev/null 2>&1 || pip install -q --user graphifyy >/dev/null 2>&1 || {
  echo "graphify: pip install graphifyy failed; /graphify will not work this session" >&2
  exit 0
}
graphify install >/dev/null 2>&1 || true
exit 0
