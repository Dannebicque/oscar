// jsdom n'implémente ni ResizeObserver ni matchMedia : FullCalendar et le shell en ont besoin.
class RO { observe() {} unobserve() {} disconnect() {} }
if (!('ResizeObserver' in globalThis)) (globalThis as unknown as { ResizeObserver: typeof RO }).ResizeObserver = RO
if (typeof matchMedia !== 'function') {
  ;(globalThis as unknown as { matchMedia: (q: string) => MediaQueryList }).matchMedia = (q: string) =>
    ({ matches: false, media: q, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {}, onchange: null, dispatchEvent: () => false }) as unknown as MediaQueryList
}

// jsdom n'implémente pas <dialog>.showModal/close : même comportement visible, sans piège de focus.
if (typeof HTMLDialogElement !== 'undefined') {
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) { this.setAttribute('open', '') }
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) { this.removeAttribute('open'); this.dispatchEvent(new Event('close')) }
}

// jsdom n'implémente pas scrollIntoView.
Element.prototype.scrollIntoView ??= function () {}
