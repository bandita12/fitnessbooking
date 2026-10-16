# Skills-Marker

agents-skills-consumed: e33c687
geprüft am: 2026-10-16

## Skill-Stand

| Skill | angewendet? | wo im Projekt | offene Position |
|---|---|---|---|
| `build-verify` | ja | `AGENTS.md §7` | |
| `web-audit` | ja | `AGENTS.md §1` | `AGENTS.todo.md` (`web-audit`-Log, kein Auftrag) |

## Offen aus dem Bereich `18623c5..e33c687`

Range: `59f9533` (neu: `web-audit`), `86d677e` + `e33c687` (beide `docs(build-verify)`).

- `build-verify` (Verifier prüft Substanz statt Gates + optionaler Milestone-Review): trifft zu, ist drin — Verify-Flow per `AGENTS.md §7` (Verifier ≠ Implementer). Die Regeländerung betrifft hier nichts: kein lokales Gate-Re-Run-Duplikat, Milestone-Review ist optional und keine Per-Feature-Pflicht. Keine offene Position.
- `web-audit` (neu: Website-Audit mit False-Positive-Gate): trifft zu — Booking-Frontend (`AGENTS.md §1`) ist künftiges Audit-Ziel; bisher keine Live-Audit-Praxis → offene Position als Log in `AGENTS.todo.md` (`web-audit`-Log, kein Auftrag).

## Fortschreiben

1. `agents-skills-consumed` ablesen → `<alt>`.
2. Im Skills-Repo die Range bilden:
   `git -C <skills-repo> log --oneline <alt>..HEAD`
   `git -C <skills-repo> log --name-only --format= <alt>..HEAD -- .agents/skills | sort -u`
3. Jeden betroffenen Skill gegen **dieses** Projekt prüfen. Trifft eine Änderung zu, wenn
   sie eine Pflicht, Schwelle, Ausnahme, Konvention oder ein Kommando betrifft, das
   **hier** gilt:
   - trifft zu und ist drin → `angewendet: ja` + `Datei:Zeile`
   - trifft zu, ist nicht drin → offene Position: Eintrag in `AGENTS.todo.md` anlegen
   - trifft nicht zu (das Projekt hat das nicht) → `angewendet: nein` + kurzer Grund
4. Offene Positionen nach `AGENTS.todo.md`; angewendete Zeilen mit `Datei:Zeile` belegen.
5. Erst wenn für **jeden** Skill aus der Range eine Zeile steht:
   `agents-skills-consumed: $(git -C <skills-repo> rev-parse --short HEAD)`,
   `geprüft am:` auf heute, Abschnitte ersetzen.
6. Falls Regeln oder Skills betroffen waren: eingebettete Regel-Kopien in der
   globalen `~/.config/opencode/AGENTS.md` nachziehen (Drift!), dann
   `touch ~/.config/opencode/opencode.jsonc`.
