import { PDFDocument } from 'pdf-lib'

export function useDigitizeCamera() {
  const videoEl = ref<HTMLVideoElement | null>(null)
  const stream = ref<MediaStream | null>(null)
  const pages = ref<Blob[]>([])
  const active = ref(false)
  const error = ref<string | null>(null)

  async function start() {
    error.value = null
    try {
      stream.value = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1920 },
          height: { ideal: 1080 },
        },
        audio: false,
      })
      active.value = true
      await nextTick()
      if (videoEl.value) {
        videoEl.value.srcObject = stream.value
        await videoEl.value.play()
      }
    }
    catch (e: any) {
      error.value = e?.message || 'Caméra inaccessible'
      active.value = false
    }
  }

  function stop() {
    stream.value?.getTracks().forEach(t => t.stop())
    stream.value = null
    active.value = false
    if (videoEl.value)
      videoEl.value.srcObject = null
  }

  async function capturePage(): Promise<Blob | null> {
    if (!videoEl.value)
      return null
    const video = videoEl.value
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 1280
    canvas.height = video.videoHeight || 720
    const ctx = canvas.getContext('2d')
    if (!ctx)
      return null
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
    const blob = await new Promise<Blob | null>(resolve =>
      canvas.toBlob(b => resolve(b), 'image/jpeg', 0.92),
    )
    if (blob)
      pages.value.push(blob)
    return blob
  }

  function removePage(index: number) {
    pages.value.splice(index, 1)
  }

  function clearPages() {
    pages.value = []
  }

  async function pagesToPdfFile(filename = 'capture-camera.pdf'): Promise<File> {
    if (!pages.value.length)
      throw new Error('Aucune page capturée')

    const pdf = await PDFDocument.create()
    for (const page of pages.value) {
      const bytes = new Uint8Array(await page.arrayBuffer())
      const image = await pdf.embedJpg(bytes)
      const sheet = pdf.addPage([image.width, image.height])
      sheet.drawImage(image, {
        x: 0,
        y: 0,
        width: image.width,
        height: image.height,
      })
    }
    const out = await pdf.save()
    return new File([out], filename, { type: 'application/pdf' })
  }

  onBeforeUnmount(stop)

  return {
    videoEl,
    stream,
    pages,
    active,
    error,
    start,
    stop,
    capturePage,
    removePage,
    clearPages,
    pagesToPdfFile,
  }
}
