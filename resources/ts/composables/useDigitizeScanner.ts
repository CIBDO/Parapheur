/**
 * Branchement scanner bureau via Dynamsoft Web TWAIN (optionnel).
 * Sans VITE_DYNAMSOFT_LICENSE, l’onglet reste informatif.
 */

declare global {
  interface Window {
    Dynamsoft?: any
  }
}

const DWT_CDN = 'https://unpkg.com/dwt@18.5.1/dist/dynamsoft.webtwain.min.js'

export function useDigitizeScanner() {
  const available = computed(() => Boolean(import.meta.env.VITE_DYNAMSOFT_LICENSE))
  const ready = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)
  let dwtObject: any = null

  async function loadSdk() {
    if (!available.value) {
      error.value = 'Scanner bureau non configuré (licence Dynamsoft absente).'
      return false
    }
    if (window.Dynamsoft?.DWT)
      return true

    loading.value = true
    error.value = null
    try {
      await new Promise<void>((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>('script[data-dwt]')
        if (existing) {
          existing.addEventListener('load', () => resolve())
          existing.addEventListener('error', () => reject(new Error('Chargement Dynamsoft impossible')))
          return
        }
        const script = document.createElement('script')
        script.src = DWT_CDN
        script.async = true
        script.dataset.dwt = '1'
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('Chargement Dynamsoft impossible'))
        document.head.appendChild(script)
      })

      const Dynamsoft = window.Dynamsoft
      Dynamsoft.DWT.ProductKey = import.meta.env.VITE_DYNAMSOFT_LICENSE
      Dynamsoft.DWT.ResourcesPath = import.meta.env.VITE_DYNAMSOFT_RESOURCE_PATH
        || 'https://unpkg.com/dwt@18.5.1/dist'
      return true
    }
    catch (e: any) {
      error.value = e?.message || 'SDK scanner indisponible'
      return false
    }
    finally {
      loading.value = false
    }
  }

  async function init(containerId = 'dwt-container') {
    const ok = await loadSdk()
    if (!ok)
      return false

    loading.value = true
    error.value = null
    try {
      await new Promise<void>((resolve, reject) => {
        window.Dynamsoft.DWT.CreateDWTObjectEx(
          {
            WebTwainId: 'archivesDigitize',
            containerId,
          },
          (obj: any) => {
            dwtObject = obj
            ready.value = true
            resolve()
          },
          (err: string) => reject(new Error(err || 'Initialisation scanner échouée')),
        )
      })
      return true
    }
    catch (e: any) {
      error.value = e?.message || 'Scanner inaccessible'
      ready.value = false
      return false
    }
    finally {
      loading.value = false
    }
  }

  async function acquire(): Promise<File[]> {
    if (!dwtObject) {
      error.value = 'Scanner non initialisé'
      return []
    }
    error.value = null
    return new Promise((resolve) => {
      dwtObject.IfShowUI = true
      dwtObject.AcquireImage(
        async () => {
          const files: File[] = []
          const count = dwtObject.HowManyImagesInBuffer
          for (let i = 0; i < count; i++) {
            const blob: Blob = await new Promise((res, rej) => {
              dwtObject.ConvertToBlob(
                [i],
                window.Dynamsoft.DWT.EnumDWT_ImageType.IT_PDF,
                (result: Blob) => res(result),
                (_c: number, msg: string) => rej(new Error(msg)),
              )
            })
            files.push(new File([blob], `scan-${i + 1}.pdf`, { type: 'application/pdf' }))
          }
          dwtObject.RemoveAllImages()
          resolve(files)
        },
        (_c: number, msg: string) => {
          error.value = msg || 'Acquisition annulée'
          resolve([])
        },
      )
    })
  }

  function destroy() {
    try {
      if (dwtObject && window.Dynamsoft?.DWT)
        window.Dynamsoft.DWT.DeleteDWTObject('archivesDigitize')
    }
    catch {
      // ignore
    }
    dwtObject = null
    ready.value = false
  }

  onBeforeUnmount(destroy)

  return {
    available,
    ready,
    loading,
    error,
    init,
    acquire,
    destroy,
  }
}
