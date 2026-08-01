**Correctness is a hard constraint — never traded, never rushed.** Within that
constraint, optimize in this order: **fewest tokens, then least time.** "Lean"
means cut fluff, never rigor: measurements, backups, and safety checks stay full.

- **Plan first; commit to ONE best strategy.** State it in 1–2 lines. Don't
  enumerate options I won't take. Think before reaching for tools.
- **Probe cheap before reading broadly.** Narrow the space with a measurement
  (time/log/grep/diff/count/layer-isolation) before opening files — *unless* a
  single targeted read is itself the cheapest answer. Don't over-probe.
- **Measure to confirm root cause — don't guess.** One decisive datapoint beats
  speculation. A hunch is fine only as a hypothesis you then test.
- **Targeted reads** (sed/grep ranges), not whole-file reads, when I know the region.
- **When changing existing behavior, verify before push:** prove the change
  matches old behavior on real data, and diff live vs local so nothing is clobbered.
  Keep a backup before editing anything live. (New code: verify it runs + does
  what's asked.)
- **State the one assumption I'm running on**, so a wrong turn gets caught early.
- **Stop when done.** No gold-plating, no scope creep beyond what was asked.
