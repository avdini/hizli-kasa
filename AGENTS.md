# Hızlı Kasa Project AI Agent Rules & Instructions

You are an AI coding assistant working on the Hızlı Kasa WooCommerce POS plugin.
Before writing any code or proposing edits, you must read the project guidelines and API standards located in the `.agents/` folder.

## CRITICAL RULES FOR ALL AI AGENTS (NO SELFISH DESIGNS)

1. **Check Documentation First:** Always read the guidelines before making any changes. The files are located at:
   - **Modern ACS Path:** `.agents/context/guidelines.md`, `.agents/skills/api-development.md`, and `.agents/context/git-standards.md`
2. **No Code Clutter / No Inline Comments:** Do not write verbose, explanatory inline comments in the code. Keep code comments minimal. Document complex architecture decisions, new endpoints, and APIs in the `.agents/` directory.
3. **API Standard (V2):** All new APIs must use Object-Oriented Programming (OOP) and inherit from `Hizli_Kasa_API_Controller_Base`. Use `Hizli_Kasa_API_Response` to format responses.
4. **No-Cache Enforcement:** Ensure all V2 API endpoints enforce no-cache headers.
5. **No Breaking Changes:** Keep legacy V1 procedural code intact unless explicitly instructed otherwise. Do not break existing API helpers.
6. **No Native Browser Dialogs (No alert / confirm / prompt):** Never use native browser popups (`window.alert`, `window.confirm`, `window.prompt`). Always use custom HTML modals (`includes/views/modals.php`) or project UI modal components (`HK.ModalManager`, SweetAlert `swal`, or custom modal overlays) for user feedback, confirmations, and inputs.
7. **Automated Release Workflow ("güncelle" Command):** When the user says **"güncelle"**, **"sürüm yayınla"**, or **"versiyon yükselt"**, immediately execute the workflow defined in `.agents/context/git-standards.md`.