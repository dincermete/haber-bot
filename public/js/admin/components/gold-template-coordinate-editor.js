const GOLD_ROW_SAMPLES = {
    purchase: ['6.020', '5.510', 'DAHİL', '201.750', '40.350', '19.740', '9.870', '6.050'],
    sale: ['6.110', '6.040', '5.090', '207.750', '41.550', '20.540', '10.270', '6.300'],
}

function num(value, fallback = 0) {
    const n = parseInt(value, 10)
    return Number.isFinite(n) ? n : fallback
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(Math.round(value), max))
}

function templateUrlFromPath(filePath) {
    if (! filePath) return ''
    const path = Array.isArray(filePath) ? filePath[0] : filePath
    if (! path || typeof path !== 'string') return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('blob:')) return path
    const clean = path.replace(/^\/+/, '')
    return clean.startsWith('storage/') ? `/${clean}` : `/storage/${clean}`
}

function initGoldTemplateCoordinateEditor(root, wire) {
    const interact = window.interact
    if (typeof interact !== 'function') {
        console.error('[gold-template-coordinate-editor] interact.js is not loaded')
        return
    }

    const canvas = root.querySelector('[data-gtce-canvas]')
    const placeholder = root.querySelector('[data-gtce-placeholder]')
    const stage = root.querySelector('[data-gtce-stage]')
    const markersHost = root.querySelector('[data-gtce-markers]')
    let img = root.querySelector('[data-gtce-image]')

    if (! canvas || ! stage || ! markersHost) return

    let canvasW = num(root.dataset.canvasW, 941)
    let canvasH = num(root.dataset.canvasH, 1796)
    let fontSize = num(root.dataset.fontSize, 22)
    let footerTimeFontSize = num(root.dataset.footerTimeFontSize, 28)
    let footerDateFontSize = num(root.dataset.footerDateFontSize, 26)
    let textColor = root.dataset.textColor || 'rgb(160, 25, 35)'

    let goldSlots = JSON.parse(root.dataset.goldSlots || '[]')
    let footerSource = JSON.parse(root.dataset.footerSource || '{"x":330,"y":1580}')
    let footerFetched = JSON.parse(root.dataset.footerFetched || '{"x":680,"y":1580}')
    const rowLabels = JSON.parse(root.dataset.rowLabels || '[]')

    function resolveCanvasSize() {
        const data = wire.data ?? {}
        canvasW = Math.max(1, num(data.canvas_width, canvasW))
        canvasH = Math.max(1, num(data.canvas_height, canvasH))
        root.dataset.canvasW = String(canvasW)
        root.dataset.canvasH = String(canvasH)
    }

    function pullFromWire() {
        const settings = wire.data?.settings ?? {}

        const rawJson = settings.gold_coordinates_json
        if (typeof rawJson === 'string' && rawJson !== '') {
            try {
                const layout = JSON.parse(rawJson)
                if (Array.isArray(layout.gold_slots) && layout.gold_slots.length) {
                    goldSlots = layout.gold_slots
                }
                if (layout.footer_source_updated) footerSource = layout.footer_source_updated
                if (layout.footer_data_fetched) footerFetched = layout.footer_data_fetched
            } catch {
                // keep dataset defaults
            }
        }

        if (Array.isArray(settings.gold_slots) && settings.gold_slots.length) {
            goldSlots = settings.gold_slots
        }
        if (settings.footer_source_updated) footerSource = settings.footer_source_updated
        if (settings.footer_data_fetched) footerFetched = settings.footer_data_fetched
        fontSize = num(settings.value_font_size, fontSize)
        footerTimeFontSize = num(settings.footer_time_font_size, footerTimeFontSize)
        footerDateFontSize = num(settings.footer_date_font_size, footerDateFontSize)
        const vc = settings.value_color
        if (vc) {
            const parts = String(vc).split(',').map((p) => num(p.trim(), 160))
            textColor = `rgb(${parts[0]}, ${parts[1]}, ${parts[2]})`
        }
    }

    function persistLayoutToWire({ syncTable = true } = {}) {
        const payload = JSON.stringify({
            gold_slots: goldSlots,
            footer_source_updated: footerSource,
            footer_data_fetched: footerFetched,
        })

        wire.set('data.settings.gold_coordinates_json', payload)
        wire.set('data.settings.gold_slots', goldSlots)
        wire.set('data.settings.footer_source_updated', footerSource)
        wire.set('data.settings.footer_data_fetched', footerFetched)

        if (syncTable) {
            syncCoordTable()
        }
    }

    function buildCoordRows() {
        const rows = {}

        for (let i = 0; i < 8; i++) {
            const slot = goldSlots[i] ?? {}
            const purchase = slot.purchase ?? { x: 0, y: 0 }
            const sale = slot.sale ?? { x: 0, y: 0 }

            rows[`g${i}p`] = {
                coordinate_key: `g${i}p`,
                label: rowLabels[i] ?? `Satır ${i + 1}`,
                field: 'Alış',
                x: num(purchase.x),
                y: num(purchase.y),
            }

            rows[`g${i}s`] = {
                coordinate_key: `g${i}s`,
                label: rowLabels[i] ?? `Satır ${i + 1}`,
                field: 'Satış',
                x: num(sale.x),
                y: num(sale.y),
            }
        }

        rows.footer_src = {
            coordinate_key: 'footer_src',
            label: 'Kaynak güncelleme (saat)',
            field: '—',
            x: num(footerSource.x),
            y: num(footerSource.y),
        }

        rows.footer_fetch = {
            coordinate_key: 'footer_fetch',
            label: 'Veri çekimi (tarih)',
            field: '—',
            x: num(footerFetched.x),
            y: num(footerFetched.y),
        }

        return rows
    }

    function syncCoordTable() {
        if (typeof Livewire === 'undefined') {
            return
        }

        Livewire.dispatch('gold-coordinates-updated', { rows: buildCoordRows() })
    }

    function applyCoordRows(rows) {
        const entries = Array.isArray(rows)
            ? rows.map((row, index) => [row.coordinate_key ?? row.__key ?? String(index), row])
            : Object.entries(rows ?? {})

        for (const [rawKey, row] of entries) {
            const key = row.coordinate_key ?? row.__key ?? rawKey
            const x = clamp(num(row.x), 0, canvasW)
            const y = clamp(num(row.y), 0, canvasH)
            const slotMatch = /^g(\d+)(p|s)$/.exec(String(key))

            if (slotMatch) {
                const index = num(slotMatch[1])
                const field = slotMatch[2] === 'p' ? 'purchase' : 'sale'

                if (! goldSlots[index]) {
                    goldSlots[index] = { purchase: { x: 0, y: 0, align: 'center' }, sale: { x: 0, y: 0, align: 'center' } }
                }

                goldSlots[index][field] = {
                    ...(goldSlots[index][field] ?? {}),
                    x,
                    y,
                    align: 'center',
                    valign: 'center',
                }

                continue
            }

            if (key === 'footer_src') {
                footerSource = { ...footerSource, x, y, align: 'left', valign: 'center' }
            }

            if (key === 'footer_fetch') {
                footerFetched = { ...footerFetched, x, y, align: 'left', valign: 'center' }
            }
        }
    }

    function syncInputRowsToEditor(rows) {
        if (! rows) {
            return
        }

        applyCoordRows(rows)
        persistLayoutToWire({ syncTable: false })
        rebuildMarkers()
    }

    function syncSlotToWire(index, field, x, y) {
        if (! goldSlots[index]) {
            goldSlots[index] = { purchase: { x: 0, y: 0, align: 'center' }, sale: { x: 0, y: 0, align: 'center' } }
        }
        if (! goldSlots[index][field]) {
            goldSlots[index][field] = { x: 0, y: 0, align: 'center' }
        }

        goldSlots[index][field].x = x
        goldSlots[index][field].y = y
        goldSlots[index][field].align = 'center'
        goldSlots[index][field].valign = 'center'

        persistLayoutToWire()
    }

    function syncFooterToWire(field, x, y) {
        if (field === 'source_updated') {
            footerSource = { ...footerSource, x, y, align: 'left', valign: 'center' }
        } else {
            footerFetched = { ...footerFetched, x, y, align: 'left', valign: 'center' }
        }

        persistLayoutToWire()
    }

    function displayScale() {
        const width = canvas.getBoundingClientRect().width
        return width <= 0 ? 1 : width / canvasW
    }

    function scaledFontSize(size = fontSize) {
        return Math.max(8, Math.round(size * displayScale()))
    }

    function toPercentX(x) {
        return `${(x / canvasW) * 100}%`
    }

    function toPercentY(y) {
        return `${(y / canvasH) * 100}%`
    }

    function applyMarkerPosition(el, x, y, { centerX = true, centerY = true } = {}) {
        el.style.left = toPercentX(x)
        el.style.top = toPercentY(y)

        if (centerX && centerY) {
            el.style.transform = 'translate(-50%, -50%)'
        } else if (centerX) {
            el.style.transform = 'translate(-50%, 0)'
        } else if (centerY) {
            el.style.transform = 'translate(0, -50%)'
        } else {
            el.style.transform = 'none'
        }
    }

    function deltaToCanvas(dx, dy) {
        const rect = canvas.getBoundingClientRect()
        if (rect.width <= 0 || rect.height <= 0) {
            return { dx, dy }
        }

        return {
            dx: (dx / rect.width) * canvasW,
            dy: (dy / rect.height) * canvasH,
        }
    }

    function createMarker({ text, x, y, className, centerX = true, centerY = true, markerFontSize = fontSize, onMove }) {
        const el = document.createElement('div')
        el.className = `gtce-marker ${className}`
        el.textContent = text
        el.style.fontSize = `${scaledFontSize(markerFontSize)}px`
        el.style.color = textColor
        applyMarkerPosition(el, x, y, { centerX, centerY })
        markersHost.appendChild(el)

        let posX = x
        let posY = y
        let dragging = false

        interact(el).draggable({
            inertia: false,
            listeners: {
                start() {
                    dragging = true
                    el.classList.add('is-dragging')
                    applyMarkerPosition(el, posX, posY, { centerX, centerY })
                },
                move(event) {
                    const delta = deltaToCanvas(event.dx, event.dy)
                    posX = clamp(posX + delta.dx, 0, canvasW)
                    posY = clamp(posY + delta.dy, 0, canvasH)
                    applyMarkerPosition(el, posX, posY, { centerX, centerY })
                    onMove(posX, posY)
                },
                end() {
                    dragging = false
                    el.classList.remove('is-dragging')
                    onMove(posX, posY)
                },
            },
        })

        return el
    }

    function rebuildMarkers() {
        markersHost.innerHTML = ''

        for (let i = 0; i < 8; i++) {
            const slot = goldSlots[i] ?? { purchase: { x: 400, y: 300 + i * 58 }, sale: { x: 650, y: 300 + i * 58 } }
            const p = slot.purchase ?? { x: 400, y: 300 + i * 58 }
            const s = slot.sale ?? { x: 650, y: 300 + i * 58 }

            createMarker({
                text: GOLD_ROW_SAMPLES.purchase[i] ?? '0.000',
                x: num(p.x, 400),
                y: num(p.y, 300 + i * 58),
                className: 'is-purchase',
                centerX: true,
                centerY: true,
                onMove: (x, y) => syncSlotToWire(i, 'purchase', x, y),
            })

            createMarker({
                text: GOLD_ROW_SAMPLES.sale[i] ?? '0.000',
                x: num(s.x, 650),
                y: num(s.y, 300 + i * 58),
                className: 'is-sale',
                centerX: true,
                centerY: true,
                onMove: (x, y) => syncSlotToWire(i, 'sale', x, y),
            })
        }

        createMarker({
            text: '17:59',
            x: num(footerSource.x, 330),
            y: num(footerSource.y, 1580),
            className: 'is-footer is-footer-time',
            centerX: false,
            centerY: true,
            markerFontSize: footerTimeFontSize,
            onMove: (x, y) => syncFooterToWire('source_updated', x, y),
        })

        createMarker({
            text: '30.06.2026',
            x: num(footerFetched.x, 680),
            y: num(footerFetched.y, 1580),
            className: 'is-footer is-footer-date',
            centerX: false,
            centerY: true,
            markerFontSize: footerDateFontSize,
            onMove: (x, y) => syncFooterToWire('data_fetched', x, y),
        })

        syncCoordTable()
    }

    function layoutCanvas() {
        resolveCanvasSize()
        canvas.style.aspectRatio = `${canvasW} / ${canvasH}`
    }

    function setTemplateVisibility(hasTemplate) {
        canvas.classList.toggle('hidden', ! hasTemplate)
        if (placeholder) placeholder.classList.toggle('hidden', hasTemplate)
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
            img.dataset.gtceImage = ''
            img.alt = 'Şablon'
            img.draggable = false
            img.className = 'gtce-template-image'
            stage.insertBefore(img, markersHost)
        }

        if (img.getAttribute('src') !== nextUrl) {
            img.src = nextUrl
        }

        layoutCanvas()
        rebuildMarkers()
    }

    wire.$watch('data.file_path', (filePath) => {
        updateTemplateImage(templateUrlFromPath(filePath))
    })

    wire.$watch('data.canvas_width', () => {
        layoutCanvas()
        rebuildMarkers()
    })

    wire.$watch('data.canvas_height', () => {
        layoutCanvas()
        rebuildMarkers()
    })

    wire.$watch('data.settings.value_font_size', (v) => {
        fontSize = num(v, fontSize)
        rebuildMarkers()
    })

    wire.$watch('data.settings.footer_time_font_size', (v) => {
        footerTimeFontSize = num(v, footerTimeFontSize)
        rebuildMarkers()
    })

    wire.$watch('data.settings.footer_date_font_size', (v) => {
        footerDateFontSize = num(v, footerDateFontSize)
        rebuildMarkers()
    })

    wire.$watch('data.settings.value_color', () => {
        pullFromWire()
        rebuildMarkers()
    })

    wire.$watch('data.settings.gold_coordinates_json', (v) => {
        if (typeof v !== 'string' || v === '') {
            return
        }

        try {
            const layout = JSON.parse(v)
            if (Array.isArray(layout.gold_slots) && layout.gold_slots.length) {
                goldSlots = layout.gold_slots
            }
            if (layout.footer_source_updated) footerSource = layout.footer_source_updated
            if (layout.footer_data_fetched) footerFetched = layout.footer_data_fetched
            rebuildMarkers()
        } catch {
            // ignore invalid json
        }
    })

    window.addEventListener('gold-coordinate-input-updated', (event) => {
        syncInputRowsToEditor(event.detail?.rows ?? event.detail?.[0]?.rows)
    })

    const initialUrl = root.dataset.templateUrl || templateUrlFromPath(wire.data?.file_path)
    pullFromWire()
    layoutCanvas()
    persistLayoutToWire()

    if (initialUrl) {
        updateTemplateImage(initialUrl)
    } else {
        setTemplateVisibility(false)
    }

    new ResizeObserver(() => rebuildMarkers()).observe(canvas)
}

export default function goldTemplateCoordinateEditor(wire) {
    return {
        init() {
            initGoldTemplateCoordinateEditor(this.$el, wire)
        },
    }
}
