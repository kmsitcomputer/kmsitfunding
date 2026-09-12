# Branching Policy

## Bootstrap Repository State

```
Current local baseline branch:   master
Target canonical stable branch:  main
Remote:                          none / not configured
```

During IMP-000 bootstrap, `master` temporarily acts as the local baseline. `main` does not
currently exist in this repository — no branch has been renamed, and this document does not
claim otherwise. The branch will not be renamed automatically by an AI agent.

Transition to `main`:

```
1. IMP-000 final gate passes (independent re-audit clean + Human approval recorded).
2. Working tree is clean.
3. Repository owner decides whether to rename `master` to `main`.
4. If renamed, update the remote's default branch after a remote exists.
5. Enable branch protection when remote hosting is configured.
6. Update CI/document references accordingly.
```

Remote branch protection cannot currently be verified because no remote or protection
configuration is available locally.

## Branches (Target Naming Convention)

```
main                          protected, stable — last approved implementation gate (target;
                               see "Bootstrap Repository State" above for the current branch)
impl/###-description          implementation work, e.g. impl/001-foundation
fix/IMP-XXX-description       bug/remediation
docs/ADR-XXX-description      architecture/document update
```

Once established, the canonical stable branch (`main`, or `master` until renamed) must always
represent the last approved implementation gate. No direct experimental work on it.

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
