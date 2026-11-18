# CLAUDE.md

## Preferences

- The backend (`backend/`) follows the **`laravel-service-pattern`** skill (`.claude/skills/laravel-service-pattern/`): Service + Repository/Gateway + DTO, thin controllers, jobs and commands. Load it before any backend change, and read the matching file in its `references/` before writing that kind of code.
  - Controllers only take a FormRequest and return a response. No business logic and no queries in them.
  - All business logic goes in **services**; all database access goes in **repositories**, each behind an interface.
  - Jobs, commands and Bitrix24 integration code call services and repositories, not models directly.
  - Security rules SEC-1…SEC-5 are always critical.
- **Migration status:** the skill's migration plan steps 1–2 (security fixes) are done. Steps 3–8 (tooling, DTOs + `toDto()`, domain exceptions, interfaces + naming, unit tests, Larastan level) are **not applied yet** to the existing code — do not start them without the user's approval. New or touched code still follows the skill.
