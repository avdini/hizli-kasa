# Hızlı Kasa UI & Notification Architecture Specification (Agent-Native Spec v2.0)

> **TARGET AGENT DIRECTIVE**: This document defines the exact architecture, JS API schemas, DOM bindings, and strict compliance rules for all UI dialogs, modals, toast notifications, and notification badges in Hızlı Kasa POS.

---

## 1. COMPLIANCE CONSTRAINTS & ZERO-TOLERANCE RULES

```yaml
rules:
  no_native_dialogs:
    forbidden_tokens:
      - "window.alert"
      - "window.confirm"
      - "window.prompt"
      - "alert("
      - "confirm("
      - "prompt("
    enforcement: "STRICT_BUILD_FAIL"
    action: "Replace with HK.UI asynchronous Promise-based methods."
  async_flow:
    pattern: "async/await"
    return_types:
      alert: "Promise<void>"
      confirm: "Promise<boolean>"
      prompt: "Promise<string|null>"
  v2_api_integration:
    error_extraction: "res.errors ? res.errors.join('<br>') : res.data?.message"
    no_cache_enforcement: true
```

---

## 2. HK.UI JAVASCRIPT API SCHEMAS & INTERFACES

### 2.1 Type Definitions (TypeScript / JSDoc Specs)

```typescript
type DialogType = 'success' | 'error' | 'warning' | 'info' | 'danger';
type InputType = 'text' | 'number' | 'textarea';
type ToastVariant = 'compact' | 'card' | 'banner';

interface AlertOptions {
    title?: string;
    message: string;
    type?: DialogType;
    confirmText?: string;
}

interface ConfirmOptions {
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    type?: DialogType;
}

interface PromptOptions {
    title?: string;
    message: string;
    placeholder?: string;
    defaultValue?: string;
    inputType?: InputType;
    required?: boolean;
    confirmText?: string;
    cancelText?: string;
}

interface ToastAction {
    label: string;
    class?: string;
    onClick: (toastInstance: { close: () => void }) => void;
}

interface ToastOptions {
    title?: string;
    message: string;
    type?: DialogType;
    variant?: ToastVariant;
    autoClose?: boolean;
    duration?: number; // default: 4000
    actions?: ToastAction[];
}
```

### 2.2 Global Method Signatures

```javascript
HK.UI = {
    // Katman 1: Blocking Interactive Modals
    alert: (options: AlertOptions) => Promise<void>,
    confirm: (options: ConfirmOptions) => Promise<boolean>,
    prompt: (options: PromptOptions) => Promise<string | null>,

    // Katman 2: Non-blocking Floating Toasts & Action Cards
    toast: (options: ToastOptions) => { close: () => void },
    toastCard: (options: ToastOptions) => { close: () => void },
    banner: (options: ToastOptions) => { close: () => void },

    // Katman 3: Notification Center & Badge State Sync
    badge: {
        set: (count: number) => void,
        increment: (step?: number) => void,
        decrement: (step?: number) => void
    }
};
```

---

## 3. DOM INJECTION & CSS SELECTOR SPECIFICATIONS

### 3.1 Global Dynamic Dialog Container (`includes/views/modals.php`)
```html
<div id="hk-global-dialog-modal" class="modal-cerceve" style="display:none; z-index: 1200;">
    <div class="modal-icerik modal-icerik-sm">
        <h3 id="hk-global-dialog-title"></h3>
        <p id="hk-global-dialog-message"></p>
        <div id="hk-global-dialog-input-wrapper" style="display:none;">
            <input type="text" id="hk-global-dialog-input" class="hk-input" autocomplete="off" />
            <textarea id="hk-global-dialog-textarea" class="hk-input" style="display:none;"></textarea>
            <small id="hk-global-dialog-error" class="hk-input-error" style="display:none;"></small>
        </div>
        <div class="modal-butonlar">
            <button id="hk-global-dialog-cancel" class="modal-btn-cancel">Vazgeç</button>
            <button id="hk-global-dialog-confirm" class="hk-btn-primary">Onayla</button>
        </div>
    </div>
</div>
<div id="hk-toast-container" class="hk-toast-stack-container"></div>
```

### 3.2 Key DOM IDs & Classes Reference
- Container: `#hk-global-dialog-modal`
- Title Element: `#hk-global-dialog-title`
- Message Element: `#hk-global-dialog-message`
- Input Element (Text/Number): `#hk-global-dialog-input`
- Textarea Element: `#hk-global-dialog-textarea`
- Error Message Element: `#hk-global-dialog-error`
- Confirm Button: `#hk-global-dialog-confirm`
- Cancel Button: `#hk-global-dialog-cancel`
- Toast Container: `#hk-toast-container`

---

## 4. SOUND & ACCESSIBILITY INTEGRATION

```json
{
  "accessibility": {
    "auto_focus": "First input or confirm button on modal display flex",
    "hotkeys": {
      "Enter": "Triggers #hk-global-dialog-confirm",
      "Escape": "Triggers #hk-global-dialog-cancel"
    },
    "touch_target_min_height": "44px",
    "receipt_modal_focus": {
      "display_focus": "#fis-yazdir-tetik (Fiş Yazdır - Enter)",
      "afterprint_restore": "Restores window.focus() and #fis-yazdir-tetik focus on browser print completion so ESC closes modal without click requirement."
    }
  },
  "sound_integration": {
    "source_state": "window.HK_DATA.soundSettings",
    "triggers": {
      "confirm_success": "HK.SoundManager.play('sharp_click')",
      "alert_error": "HK.SoundManager.play('high_alert')",
      "toast_success": "HK.SoundManager.play('soft')"
    }
  }
}
```

---

## 5. NOTIFICATION CENTER & EVENT ROUTING ARCHITECTURE

For multi-warehouse, real-time, and role-based notification center specifications, database schema, V2 API endpoints, and event routing triggers (e.g., stock transfer alerts, e-commerce sale stock depletion notices), see:
- [notification-center-architecture.md](file:///c:/Users/fikri/Desktop/avdini.com/hizli-kasa/.agents/context/notification-center-architecture.md)
