import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import QuickCaptureDialog from '../components/layout/QuickCaptureDialog.vue'
import { nativeNotify, isDesktop, onNative, readStoredToken, storeToken } from '../desktop/bridge'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'
import { mountApp } from './helpers'

type W = { __TAURI__?: unknown }
afterEach(() => { delete (window as unknown as W).__TAURI__; document.body.innerHTML = '' })

describe('application de bureau (Tauri) : le pont reste inerte dans le navigateur', () => {
  it('sans Tauri, rien ne se passe et rien ne plante', async () => {
    expect(isDesktop()).toBe(false)
    expect(await readStoredToken()).toBeNull()
    await storeToken('abc')
    expect(await nativeNotify('Oscar', 'x')).toBe(false)
    expect(await onNative('oscar://quick-capture', () => {})).toBeTypeOf('function')
  })

  it('avec Tauri : le jeton va dans le trousseau, les notifications sont natives, le raccourci global est écouté', async () => {
    const invoke = vi.fn(async (cmd: string) => (cmd === 'token_get' ? 'jeton-du-trousseau' : undefined))
    const sendNotification = vi.fn()
    let handler: (() => void) | undefined
    ;(window as unknown as W).__TAURI__ = {
      core: { invoke },
      event: { listen: async (_n: string, cb: () => void) => { handler = cb; return () => {} } },
      notification: { isPermissionGranted: async () => false, requestPermission: async () => 'granted', sendNotification },
    }
    expect(isDesktop()).toBe(true)
    expect(await readStoredToken()).toBe('jeton-du-trousseau')
    await storeToken('nouveau')
    expect(invoke).toHaveBeenCalledWith('token_set', { token: 'nouveau' })
    await storeToken(null)
    expect(invoke).toHaveBeenCalledWith('token_delete', undefined)
    expect(await nativeNotify('Oscar', 'Sophie a répondu')).toBe(true)
    expect(sendNotification).toHaveBeenCalledWith({ title: 'Oscar', body: 'Sophie a répondu' })
    const opened = vi.fn()
    await onNative('oscar://quick-capture', opened)
    handler?.()
    expect(opened).toHaveBeenCalled()
  })
})

describe('capture rapide (N-03)', () => {
  it('une tâche en deux gestes : on ouvre la fenêtre, on tape, Entrée', async () => {
    const { wrapper, pinia } = await mountApp(QuickCaptureDialog, '/')
    useUiStore(pinia).quickCaptureOpen = true
    await flushPromises()
    const input = document.querySelector<HTMLInputElement>('#quick-title')!
    input.value = 'Appeler la scolarité'
    input.dispatchEvent(new Event('input'))
    document.querySelector<HTMLFormElement>('#quick-form')!.dispatchEvent(new Event('submit', { cancelable: true }))
    await flushPromises()
    expect(useTasksStore(pinia).items.some((t) => t.title === 'Appeler la scolarité')).toBe(true)
    expect(useUiStore(pinia).quickCaptureOpen).toBe(false)
    wrapper.unmount()
  })
})
