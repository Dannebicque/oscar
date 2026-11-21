//! Oscar, application de bureau (Tauri 2).
//!
//! Le bureau n'ajoute pas de logique métier : il charge le même front que le navigateur et apporte seulement ce qu'un navigateur ne peut pas :
//! - le jeton de session dans le trousseau du système (N-04), jamais dans un fichier ni dans localStorage ;
//! - les notifications natives (F-75), pilotées par le front (canal « bureau » des réglages) ;
//! - un raccourci global de capture rapide d'une tâche (N-03) ;
//! - une icône dans la barre système (ouvrir, capture rapide, quitter).
//! Rien ne s'envoie, rien ne se ferme, rien ne se relance tout seul : comme dans le navigateur, l'utilisateur décide.

use tauri::{
    menu::{Menu, MenuItem},
    tray::{MouseButton, MouseButtonState, TrayIconBuilder, TrayIconEvent},
    AppHandle, Emitter, Manager,
};
use tauri_plugin_global_shortcut::{Code, GlobalShortcutExt, Modifiers, Shortcut, ShortcutState};

const SERVICE: &str = "fr.oscar.desktop";
const ACCOUNT: &str = "session";
/// Événement reçu par le front (web/src/desktop/bridge.ts) pour ouvrir la capture rapide.
const QUICK_CAPTURE: &str = "oscar://quick-capture";

fn entry() -> Result<keyring::Entry, String> {
    keyring::Entry::new(SERVICE, ACCOUNT).map_err(|e| e.to_string())
}

/// Jeton de session stocké dans le trousseau, ou `None` s'il n'y en a pas.
#[tauri::command]
fn token_get() -> Result<Option<String>, String> {
    match entry()?.get_password() {
        Ok(t) => Ok(Some(t)),
        Err(keyring::Error::NoEntry) => Ok(None),
        Err(e) => Err(e.to_string()),
    }
}

#[tauri::command]
fn token_set(token: String) -> Result<(), String> {
    entry()?.set_password(&token).map_err(|e| e.to_string())
}

#[tauri::command]
fn token_delete() -> Result<(), String> {
    match entry()?.delete_credential() {
        Ok(()) | Err(keyring::Error::NoEntry) => Ok(()),
        Err(e) => Err(e.to_string()),
    }
}

/// Ramène la fenêtre au premier plan (depuis la barre système ou le raccourci global).
fn show_main(app: &AppHandle) {
    if let Some(w) = app.get_webview_window("main") {
        let _ = w.show();
        let _ = w.unminimize();
        let _ = w.set_focus();
    }
}

fn quick_capture(app: &AppHandle) {
    show_main(app);
    let _ = app.emit(QUICK_CAPTURE, ());
}

pub fn run() {
    let capture = Shortcut::new(Some(Modifiers::SUPER | Modifiers::SHIFT), Code::Space);
    let capture_alt = Shortcut::new(Some(Modifiers::CONTROL | Modifiers::SHIFT), Code::Space);

    tauri::Builder::default()
        // Une seule fenêtre : relancer l'application ramène la fenêtre existante.
        .plugin(tauri_plugin_single_instance::init(|app, _args, _cwd| show_main(app)))
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_notification::init())
        .plugin(
            tauri_plugin_global_shortcut::Builder::new()
                .with_handler(|app, _shortcut, event| {
                    if event.state() == ShortcutState::Pressed {
                        quick_capture(app);
                    }
                })
                .build(),
        )
        .invoke_handler(tauri::generate_handler![token_get, token_set, token_delete])
        .setup(move |app| {
            // Un raccourci déjà pris par le système ou une autre application ne doit pas empêcher Oscar de démarrer.
            let _ = app.global_shortcut().register(capture);
            let _ = app.global_shortcut().register(capture_alt);
            let open = MenuItem::with_id(app, "open", "Ouvrir Oscar", true, None::<&str>)?;
            let capture_item = MenuItem::with_id(app, "capture", "Capture rapide", true, None::<&str>)?;
            let quit = MenuItem::with_id(app, "quit", "Quitter", true, None::<&str>)?;
            let menu = Menu::with_items(app, &[&open, &capture_item, &quit])?;
            TrayIconBuilder::new()
                .icon(app.default_window_icon().expect("icône absente").clone())
                .tooltip("Oscar")
                .menu(&menu)
                .show_menu_on_left_click(false)
                .on_menu_event(|app, event| match event.id.as_ref() {
                    "open" => show_main(app),
                    "capture" => quick_capture(app),
                    "quit" => app.exit(0),
                    _ => {}
                })
                .on_tray_icon_event(|tray, event| {
                    if let TrayIconEvent::Click { button: MouseButton::Left, button_state: MouseButtonState::Up, .. } = event {
                        show_main(tray.app_handle());
                    }
                })
                .build(app)?;
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("erreur au lancement d'Oscar");
}
