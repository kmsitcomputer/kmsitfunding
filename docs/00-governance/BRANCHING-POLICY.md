# Branching Policy

## Branches

```
main                        protected, stable — last approved implementation gate
impl/###-description        implementation work, e.g. impl/001-foundation
fix/IMP-XXX-description      bug/remediation
docs/ADR-XXX-description     architecture/document update
```

`main` must always represent the last approved implementation gate. No direct experimental work
on `main`.

## Worktree Policy

When multiple agents need parallel read/review:

```
main
├── worktree-claude-IMP-xxx        READ + WRITE (Claude)
└── worktree-codex-review-IMP-xxx  READ / REVIEW (Codex, unless remediation ownership
                                    is explicitly transferred)
```

## Ownership Rule

One file, one active owner. One task, one primary implementation agent at a time. See
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) for the full AI role assignment and
the reasoning behind this rule.

## Commit Strategy

Prefer coherent, conventional commits over one giant commit:

```
feat(identity): bootstrap user authentication foundation
test(identity): add authentication feature tests
docs(identity): update implementation notes
```

Not:

```
"build entire platform"
```

## Migration Immutability

After a migration reaches shared/stable history, prefer a new corrective migration rather than
rewriting history. During early unpublished feature branches, squashing may be allowed before
merge according to team policy.
