#!/usr/bin/env python3
"""Check common untranslated Danish source strings in BC Staff custom code.

This is a supplementary heuristic, not a replacement for code review or
Drupal translation checks.
"""

from pathlib import Path
import re
import sys

root = Path("web/modules/custom")
extensions = {".php", ".module", ".install", ".inc", ".yml", ".yaml", ".twig", ".js"}
markers = re.compile(
    r"\b(medarbejdere|medarbejdernavn|afdeling|afdelinger|opret medarbejder|"
    r"rediger medarbejder|gem min status|ikke angivet|til stede|"
    r"lokationer|brugernavn|log ind som|blokeret)\b",
    re.IGNORECASE,
)
violations = []
for path in root.rglob("*"):
    if not path.is_file() or path.suffix not in extensions:
        continue
    for number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
        # URL route paths are stable identifiers, not user-facing source text.
        if path.name.endswith(".routing.yml") and line.lstrip().startswith("path:"):
            continue
        if markers.search(line):
            violations.append(f"{path}:{number}: possible Danish source text")
if violations:
    print("\n".join(violations))
    print(f"FAIL: {len(violations)} likely non-English source lines.")
    sys.exit(1)
print("PASS: No known Danish markers found (heuristic only).")
