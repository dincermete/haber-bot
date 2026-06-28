function num(value, fallback = 0) {
    const n = parseInt(value, 10)
    return Number.isFinite(n) ? n : fallback
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(Math.round(value), max))
}

function templateUrlFromPath(filePath) {
    if (! filePath) {
        return ''
    }

    const path = Array.isArray(filePath) ? filePath[0] : filePath

    if (! path || typeof path !== 'string') {
        return ''
    }

    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('blob:')) {
        return path
    }

    const clean = path.replace(/^\/+/, '')

    if (clean.startsWith('storage/')) {
        return `/${clean}`
    }

    return `/storage/${clean}`
}

function getWireModelPath(element) {
    if (! element?.attributes) {
        return null
    }

    for (const attr of element.attributes) {
        if (attr.name.startsWith('wire:model')) {
            return attr.value
        }
    }

    return null
}

function initTemplateCoordinateEditor(root, wire) {
    const interact = window.interact

    if (typeof interact !== 'function') {
        console.error('[template-coordinate-editor] interact.js is not loaded')
        return
    }

    const canvas = root.querySelector('[data-tce-canvas]')
    const placeholder = root.querySelector('[data-tce-placeholder]')
    const stage = root.querySelector('[data-tce-stage]')
    const label = root.querySelector('[data-tce-label]')
    const padGuide = root.querySelector('[data-tce-padding-guide]')
    let img = root.querySelector('[data-tce-image]')
    const coordX = root.querySelector('[data-tce-x]')
    const coordY = root.querySelector('[data-tce-y]')

    if (! canvas || ! stage || ! label) {
        return
    }

    let canvasW = num(root.dataset.canvasW, 1080)
    let canvasH = num(root.dataset.canvasH, 1080)
    let fontSize = num(root.dataset.fontSize, 48)
    let lineHeight = fontSize + 10
    let padding = num(root.dataset.padding, 60)
    let wrapWidth = num(root.dataset.wrapWidth, 40)
    let textColor = root.dataset.textColor || 'rgb(255,255,255)'
    let posX = num(root.dataset.posX, 60)
    let posY = num(root.dataset.posY, 720)
    let dragging = false

    function resolveCanvasSize() {
        const data = wire.data ?? {}

        canvasW = Math.max(1, num(data.canvas_width, num(root.dataset.canvasW, 1080)))
        canvasH = Math.max(1, num(data.canvas_height, num(root.dataset.canvasH, 1080)))

        root.dataset.canvasW = String(canvasW)
        root.dataset.canvasH = String(canvasH)
    }

    function displayScale() {
        const width = canvas.getBoundingClientRect().width

        if (width <= 0) {
            return 1
        }

        return width / canvasW
    }

    function layoutStage() {
        const s = displayScale()

        canvas.style.aspectRatio = `${canvasW} / ${canvasH}`
        stage.style.width = `${canvasW}px`
        stage.style.height = `${canvasH}px`
        stage.style.transform = `scale(${s})`

        return s
    }

    function isSettingPath(model, key) {
        return model === `data.settings.${key}` || model.endsWith(`.settings.${key}`)
    }

    function bounds() {
        const innerW = Math.max(0, canvasW - padding * 2)
        const innerH = Math.max(0, canvasH - padding * 2)

        return {
            minX: padding,
            minY: padding,
            maxX: padding + innerW,
            maxY: padding + innerH,
        }
    }

    function enforceBounds() {
        const b = bounds()
        const nextX = clamp(posX, b.minX, b.maxX)
        const nextY = clamp(posY, b.minY, b.maxY)
        const changed = nextX !== posX || nextY !== posY
        posX = nextX
        posY = nextY

        return changed
    }

    function textMaxWidth() {
        const inner = Math.max(80, canvasW - padding * 2)
        const wrapPx = Math.round(wrapWidth * fontSize * 0.55)
        const fromSetting = Math.min(inner, wrapPx)
        const fromPosition = Math.max(80, canvasW - padding - posX)

        return Math.min(fromSetting, fromPosition)
    }

    function setTemplateVisibility(hasTemplate) {
        canvas.classList.toggle('hidden', ! hasTemplate)
        if (placeholder) {
            placeholder.classList.toggle('hidden', hasTemplate)
        }
    }

    function bindImageLoadHandlers(imageEl) {
        const onImageLoad = () => {
            const data = wire.data ?? {}

            if (! num(data.canvas_width, 0) && imageEl.naturalWidth > 0) {
                const naturalW = imageEl.naturalWidth
                const naturalH = imageEl.naturalHeight
                const exportW = 1080
                const exportH = Math.max(1, Math.round(exportW * naturalH / naturalW))
                wire.set('data.canvas_width', exportW)
                wire.set('data.canvas_height', exportH)
            }

            applyStyles()
        }

        if (imageEl.complete && imageEl.naturalWidth > 0) {
            onImageLoad()
        } else {
            imageEl.addEventListener('load', onImageLoad, { once: true })
        }
    }

    function updateTemplateImage(url) {
        const nextUrl = (url || '').trim()

        if (! nextUrl) {
            setTemplateVisibility(false)
            return
        }

        setTemplateVisibility(true)

        if (! img) {
            img = document.createElement('img')
            img.dataset.tceImage = ''
            img.alt = 'Şablon'
            img.draggable = false
            img.className = 'pointer-events-none block h-full w-full select-none object-fill'
            stage.insertBefore(img, stage.firstChild)
        }

        if (img.getAttribute('src') === nextUrl) {
            applyStyles()
            return
        }

        img.onload = () => bindImageLoadHandlers(img)
        img.src = nextUrl
        root.dataset.templateUrl = nextUrl
    }

    function applyStyles() {
        resolveCanvasSize()
        enforceBounds()
        layoutStage()

        label.style.left = `${posX}px`
        label.style.top = `${posY}px`
        label.style.fontSize = `${fontSize}px`
        label.style.lineHeight = `${lineHeight}px`
        label.style.maxWidth = `${textMaxWidth()}px`
        label.style.color = textColor

        if (padGuide) {
            padGuide.style.left = `${padding}px`
            padGuide.style.top = `${padding}px`
            padGuide.style.width = `${Math.max(0, canvasW - padding * 2)}px`
            padGuide.style.height = `${Math.max(0, canvasH - padding * 2)}px`
        }

        if (coordX) {
            coordX.textContent = String(posX)
        }
        if (coordY) {
            coordY.textContent = String(posY)
        }
    }

    function pullSettings(settings) {
        if (! settings || typeof settings !== 'object') {
            return
        }

        fontSize = num(settings.font_size, fontSize)
        lineHeight = fontSize + 10
        padding = num(settings.padding, padding)
        wrapWidth = num(settings.wrap_width, wrapWidth)

        if (! dragging) {
            posX = num(settings.text_x, posX)
            posY = num(settings.text_y, posY)
        }

        const titleColor = settings.title_color
        if (titleColor) {
            const parts = String(titleColor).split(',').map((part) => num(part.trim(), 255))
            textColor = `rgb(${parts[0]}, ${parts[1]}, ${parts[2]})`
        }
    }

    function syncFromWire() {
        pullSettings(wire.data?.settings)

        if (! dragging) {
            posX = num(wire.data?.settings?.text_x, posX)
            posY = num(wire.data?.settings?.text_y, posY)
        }

        applyStyles()
    }

    function syncWire() {
        wire.set('data.settings.text_x', posX)
        wire.set('data.settings.text_y', posY)
    }

    function setCanvasPosition(x, y) {
        const b = bounds()
        posX = clamp(x, b.minX, b.maxX)
        posY = clamp(y, b.minY, b.maxY)
        applyStyles()
        syncWire()
    }

    function pointerToCanvas(clientX, clientY) {
        const rect = canvas.getBoundingClientRect()
        const s = displayScale()

        return {
            x: (clientX - rect.left) / s,
            y: (clientY - rect.top) / s,
        }
    }

    function bindSettingsInputs() {
        const page = root.closest('[wire\\:id]')
        if (! page || page.dataset.tceInputsBound === '1') {
            return
        }

        page.dataset.tceInputsBound = '1'

        const handleFieldChange = (event) => {
            const input = event.target.closest('input')
            if (! input) {
                return
            }

            const model = getWireModelPath(input)
            if (! model) {
                return
            }

            if (! model.includes('settings.') && ! model.includes('canvas_width') && ! model.includes('canvas_height')) {
                return
            }

            if (isSettingPath(model, 'font_size')) {
                fontSize = num(input.value, fontSize)
                lineHeight = fontSize + 10
            } else if (isSettingPath(model, 'padding')) {
                padding = num(input.value, padding)
                if (enforceBounds() && ! dragging) {
                    syncWire()
                }
            } else if (isSettingPath(model, 'wrap_width')) {
                wrapWidth = num(input.value, wrapWidth)
            } else if (isSettingPath(model, 'title_color')) {
                const parts = String(input.value).split(',').map((part) => num(part.trim(), 255))
                textColor = `rgb(${parts[0]}, ${parts[1]}, ${parts[2]})`
            } else if (! dragging && isSettingPath(model, 'text_x')) {
                posX = num(input.value, posX)
            } else if (! dragging && isSettingPath(model, 'text_y')) {
                posY = num(input.value, posY)
            }

            applyStyles()
        }

        page.addEventListener('input', handleFieldChange)
        page.addEventListener('change', handleFieldChange)
    }

    interact(label).draggable({
        inertia: false,
        listeners: {
            start() {
                dragging = true
                label.classList.add('is-dragging')
            },
            move(event) {
                const s = displayScale()
                const b = bounds()

                posX = clamp(posX + event.dx / s, b.minX, b.maxX)
                posY = clamp(posY + event.dy / s, b.minY, b.maxY)

                applyStyles()
                syncWire()
            },
            end() {
                dragging = false
                label.classList.remove('is-dragging')
                syncWire()
            },
        },
    })

    canvas.addEventListener('click', (event) => {
        if (dragging || event.target.closest('[data-tce-label]')) {
            return
        }

        const point = pointerToCanvas(event.clientX, event.clientY)
        setCanvasPosition(point.x, point.y)
    })

    if (img) {
        bindImageLoadHandlers(img)
    }

    wire.$watch('data.file_path', (filePath) => {
        updateTemplateImage(templateUrlFromPath(filePath))
    })

    const initialTemplateUrl = root.dataset.templateUrl || templateUrlFromPath(wire.data?.file_path)
    if (initialTemplateUrl) {
        updateTemplateImage(initialTemplateUrl)
    } else {
        setTemplateVisibility(false)
    }

    new ResizeObserver(() => applyStyles()).observe(canvas)

    wire.$watch('data.settings.text_x', (value) => {
        if (! dragging) {
            posX = num(value, posX)
            applyStyles()
        }
    })

    wire.$watch('data.settings.text_y', (value) => {
        if (! dragging) {
            posY = num(value, posY)
            applyStyles()
        }
    })

    wire.$watch('data.settings.font_size', (value) => {
        fontSize = num(value, fontSize)
        lineHeight = fontSize + 10
        applyStyles()
    })

    wire.$watch('data.settings.padding', (value) => {
        padding = num(value, padding)
        if (enforceBounds() && ! dragging) {
            syncWire()
        }
        applyStyles()
    })

    wire.$watch('data.settings.wrap_width', (value) => {
        wrapWidth = num(value, wrapWidth)
        applyStyles()
    })

    wire.$watch('data.settings.title_color', (value) => {
        if (! value) {
            return
        }

        const parts = String(value).split(',').map((part) => num(part.trim(), 255))
        textColor = `rgb(${parts[0]}, ${parts[1]}, ${parts[2]})`
        applyStyles()
    })

    wire.$watch('data.canvas_width', () => applyStyles())
    wire.$watch('data.canvas_height', () => applyStyles())

    bindSettingsInputs()

    const registerLivewireHook = () => {
        if (! window.Livewire) {
            return
        }

        Livewire.hook('commit', ({ component, succeed }) => {
            succeed(() => {
                if (component.el?.contains?.(root)) {
                    requestAnimationFrame(() => syncFromWire())
                }
            })
        })
    }

    document.addEventListener('livewire:init', registerLivewireHook)
    registerLivewireHook()

    const boot = () => {
        if (canvas.getBoundingClientRect().width > 0) {
            requestAnimationFrame(() => syncFromWire())
            return
        }

        requestAnimationFrame(boot)
    }

    boot()
}

export default function templateCoordinateEditor(wire) {
    return {
        init() {
            initTemplateCoordinateEditor(this.$el, wire)
        },
    }
}
