# CLAUDE.md

## Preferences

- The backend (`backend/`) follows the **`laravel-service-pattern`** skill (`.claude/skills/laravel-service-pattern/`): Service + Repository/Gateway + DTO, thin controllers, jobs and commands. Load it before any backend change, and read the matching file in its `references/` before writing that kind of code.
  - Controllers only take a FormRequest and return a response. No business logic and no queries in them.
  - All business logic goes in **services**; all database access goes in **repositories**, each behind an interface.
  - Jobs, commands and Bitrix24 integration code call services and repositories, not models directly.
  - Security rules SEC-1…SEC-5 are always critical.
- **Migration status:** the skill's migration plan steps 1–8 are done: steps 1–2 on `main`, steps 3–8 in PR4–PR10 (#58–#77, stacked, awaiting merge in order). Larastan runs at level 9 with no baseline. The violations still left are tracked in `SKILL_COMPLIANCE_FIXES.md` (PR11a–PR11k); follow it one step at a time, with the user's approval before each step. New or touched code follows the skill.

## Accepted deviations from the skill

The project owner approved these on 2026-11-23 (`SKILL_COMPLIANCE_FIXES.md`, section G). Do not "fix" them without the owner's approval.

- **QUE-6: writes to Bitrix24 run inside the HTTP request.** A change Bitrix24 refuses is not saved here either, and the user sees why at once (Bitrix24 plan step 3.1). The gateway's timeout (`BITRIX24_TIMEOUT`, default 30 s) bounds the wait. Background work (imports, Bitrix events, the scheduled syncs) still goes through the `bitrix` queue. Moving these writes to jobs would change behaviour, and it also decides the shape of the Bitrix24 plan step 5.2 (dead letters).
- **ARC-1, in part: the HTTP side of the Bitrix24 OAuth stays in `BitrixAuthController`.** Keeping the session state, comparing the host and the redirects are HTTP concerns and stay there. Checking the returned `state` is input validation, so it moves to a FormRequest (`SKILL_COMPLIANCE_FIXES.md` step B.6).
