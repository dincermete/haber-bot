const WEATHER_FIELD_KEYS = ['temperature', 'humidity', 'wind_speed']
const WEATHER_FIELD_SUFFIXES = ['t', 'h', 'w']
const WEATHER_FIELD_LABELS = {
    temperature: 'Sıcaklık',
    humidity: 'Nem',
    wind_speed: 'Rüzgar',
}
const WEATHER_SAMPLES = {
    temperature: ['18°C', '12°C', '11°C', '10°C', '9°C', '8°C', '7°C', '6°C', '5°C', '4°C', '3°C'],
    humidity: ['%65', '%70', '%68', '%72', '%75', '%73', '%71', '%69', '%67', '%66', '%64'],
    wind_speed: ['5.2 km/s', '4.1 km/s', '3.8 km/s', '3.5 km/s', '3.2 km/s', '3.0 km/s', '2.8 km/s', '2.6 km/s', '2.4 km/s', '2.2 km/s', '2.0 km/s'],
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

function initWeatherTemplateCoordinateEditor(root, wire) {
    const interact = window.interact
    if (typeof interact !== 'function') {
        console.error('[weather-template-coordinate-editor] interact.js is not loaded')
        return
    }

    const canvas = root.querySelector('[data-wtce-canvas]')
    const placeholder = root.querySelector('[data-wtce-placeholder]')
    const stage = root.querySelector('[data-wtce-stage]')
    const markersHost = root.querySelector('[data-wtce-markers]')
    let img = root.querySelector('[data-wtce-image]')

    if (! canvas || ! stage || ! markersHost) return

    let canvasW = num(root.dataset.canvasW, 1080)
    let canvasH = num(root.dataset.canvasH, 1920)
    let fontSize = num(root.dataset.fontSize, 20)
    let merkezTempFontSize = num(root.dataset.merkezTempFontSize, 42)
    let headerDateFontSize = num(root.dataset.headerDateFontSize, 24)
    let textColor = root.dataset.textColor || 'rgb(30, 50, 90)'

    let weatherSlots = JSON.parse(root.dataset.weatherSlots || '[]')
    let headerDate = JSON.parse(root.dataset.headerDate || '{"x":540,"y":180}')
    const districtLabels = JSON.parse(root.dataset.districtLabels || '[]')
    const districtCount = Math.max(districtLabels.length, weatherSlots.length, 11)

    function resolveCanvasSize() {
        const data = wire.data ?? {}
        canvasW = Math.max(1, num(data.canvas_width, canvasW))
        canvasH = Math.max(1, num(data.canvas_height, canvasH))
        root.dataset.canvasW = String(canvasW)
        root.dataset.canvasH = String(canvasH)
    }

    function pullFromWire() {
        const settings = wire.data?.settings ?? {}

        const rawJson = settings.weather_coordinates_json
        if (typeof rawJson === 'string' && rawJson !== '') {
            try {
                const layout = JSON.parse(rawJson)
                if (Array.isArray(layout.weather_slots) && layout.weather_slots.length) {
                    weatherSlots = layout.weather_slots
                }
                if (layout.header_date) headerDate = layout.header_date
            } catch {
                // keep dataset defaults
            }
        }

        if (Array.isArray(settings.weather_slots) && settings.weather_slots.length) {
            weatherSlots = settings.weather_slots
        }
        if (settings.header_date) headerDate = settings.header_date
        fontSize = num(settings.value_font_size, fontSize)
        merkezTempFontSize = num(settings.merkez_temperature_font_size, merkezTempFontSize)
        headerDateFontSize = num(settings.header_date_font_size, headerDateFontSize)
        const vc = settings.value_color
        if (vc) {
            const parts = String(vc).split(',').map((p) => num(p.trim(), 30))
            textColor = `rgb(${parts[0]}, ${parts[1]}, ${parts[2]})`
        }
    }

    function persistLayoutToWire({ syncTable = true } = {}) {
        const payload = JSON.stringify({
            weather_slots: weatherSlots,
            header_date: headerDate,
        })

        wire.set('data.settings.weather_coordinates_json', payload)
        wire.set('data.settings.weather_slots', weatherSlots)
        wire.set('data.settings.header_date', headerDate)

        if (syncTable) {
            syncCoordTable()
        }
    }

    function buildCoordRows() {
        const rows = {}

        for (let i = 0; i < districtCount; i++) {
            const slot = weatherSlots[i] ?? {}

            WEATHER_FIELD_KEYS.forEach((fieldKey, fieldIndex) => {
                const coord = slot[fieldKey] ?? { x: 0, y: 0 }
                const key = `w${i}${WEATHER_FIELD_SUFFIXES[fieldIndex]}`

                rows[key] = {
                    coordinate_key: key,
                    label: districtLabels[i] ?? `İlçe ${i + 1}`,
                    field: WEATHER_FIELD_LABELS[fieldKey],
                    x: num(coord.x),
                    y: num(coord.y),
                }
            })
        }

        rows.header_date = {
            coordinate_key: 'header_date',
            label: 'Tarih',
            field: '—',
            x: num(headerDate.x),
            y: num(headerDate.y),
        }

        return rows
    }

    function syncCoordTable() {
        if (typeof Livewire === 'undefined') {
            return
        }

        Livewire.dispatch('weather-coordinates-updated', { rows: buildCoordRows() })
    }

    function applyCoordRows(rows) {
        const entries = Array.isArray(rows)
            ? rows.map((row, index) => [row.coordinate_key ?? row.__key ?? String(index), row])
            : Object.entries(rows ?? {})

        for (const [rawKey, row] of entries) {
            const key = row.coordinate_key ?? row.__key ?? rawKey
            const x = clamp(num(row.x), 0, canvasW)
            const y = clamp(num(row.y), 0, canvasH)
            const slotMatch = /^w(\d+)(t|h|w)$/.exec(String(key))

            if (slotMatch) {
                const index = num(slotMatch[1])
                const fieldIndex = WEATHER_FIELD_SUFFIXES.indexOf(slotMatch[2])
                const fieldKey = WEATHER_FIELD_KEYS[fieldIndex]

                if (! fieldKey) {
                    continue
                }

                if (! weatherSlots[index]) {
                    weatherSlots[index] = {
                        temperature: { x: 0, y: 0, align: 'center', valign: 'center' },
                        humidity: { x: 0, y: 0, align: 'center', valign: 'center' },
                        wind_speed: { x: 0, y: 0, align: 'center', valign: 'center' },
                    }
                }

                const align = index === 0 && fieldKey !== 'temperature' ? 'left' : 'center'

                weatherSlots[index][fieldKey] = {
                    ...(weatherSlots[index][fieldKey] ?? {}),
                    x,
                    y,
                    align,
                    valign: 'center',
                }

                continue
            }

            if (key === 'header_date') {
                headerDate = { ...headerDate, x, y, align: 'center', valign: 'center' }
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

    function syncSlotToWire(index, fieldKey, x, y) {
        if (! weatherSlots[index]) {
            weatherSlots[index] = {
                temperature: { x: 0, y: 0, align: 'center', valign: 'center' },
                humidity: { x: 0, y: 0, align: 'center', valign: 'center' },
                wind_speed: { x: 0, y: 0, align: 'center', valign: 'center' },
            }
        }

        const align = index === 0 && fieldKey !== 'temperature' ? 'left' : 'center'

        weatherSlots[index][fieldKey] = {
            ...(weatherSlots[index][fieldKey] ?? {}),
            x,
            y,
            align,
            valign: 'center',
        }

        persistLayoutToWire()
    }

    function syncHeaderDateToWire(x, y) {
        headerDate = { ...headerDate, x, y, align: 'center', valign: 'center' }
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

    function markerClassForField(fieldKey) {
        if (fieldKey === 'humidity') return 'is-humidity'
        if (fieldKey === 'wind_speed') return 'is-wind'
        return 'is-temperature'
    }

    function createMarker({ text, x, y, className, centerX = true, centerY = true, markerFontSize = fontSize, onMove }) {
        const el = document.createElement('div')
        el.className = `wtce-marker ${className}`
        el.textContent = text
        el.style.fontSize = `${scaledFontSize(markerFontSize)}px`
        el.style.color = textColor
        applyMarkerPosition(el, x, y, { centerX, centerY })
        markersHost.appendChild(el)

        let posX = x
        let posY = y

        interact(el).draggable({
            inertia: false,
            listeners: {
                start() {
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
                    el.classList.remove('is-dragging')
                    onMove(posX, posY)
                },
            },
        })

        return el
    }

    function defaultCoord(index, fieldKey) {
        const gridStartY = Math.round(canvasH * 0.27)
        const rowHeight = Math.round(canvasH * 0.038)
        const defaults = {
            temperature: { x: Math.round(canvasW * 0.48), y: gridStartY + ((index - 1) * rowHeight) },
            humidity: { x: Math.round(canvasW * 0.60), y: gridStartY + ((index - 1) * rowHeight) },
            wind_speed: { x: Math.round(canvasW * 0.74), y: gridStartY + ((index - 1) * rowHeight) },
        }

        if (index === 0) {
            return {
                temperature: { x: Math.round(canvasW * 0.68), y: Math.round(canvasH * 0.195) },
                humidity: { x: Math.round(canvasW * 0.26), y: Math.round(canvasH * 0.215) },
                wind_speed: { x: Math.round(canvasW * 0.26), y: Math.round(canvasH * 0.235) },
            }[fieldKey]
        }

        return defaults[fieldKey]
    }

    function rebuildMarkers() {
        markersHost.innerHTML = ''

        for (let i = 0; i < districtCount; i++) {
            const slot = weatherSlots[i] ?? {}

            WEATHER_FIELD_KEYS.forEach((fieldKey) => {
                const coord = slot[fieldKey] ?? defaultCoord(i, fieldKey)
                const centerX = ! (i === 0 && fieldKey !== 'temperature')
                const markerFontSize = fieldKey === 'temperature' && i === 0 ? merkezTempFontSize : fontSize

                createMarker({
                    text: WEATHER_SAMPLES[fieldKey][i] ?? '—',
                    x: num(coord.x),
                    y: num(coord.y),
                    className: markerClassForField(fieldKey),
                    centerX,
                    centerY: true,
                    markerFontSize,
                    onMove: (x, y) => syncSlotToWire(i, fieldKey, x, y),
                })
            })
        }

        createMarker({
            text: '09.07.2026',
            x: num(headerDate.x, Math.round(canvasW * 0.5)),
            y: num(headerDate.y, Math.round(canvasH * 0.094)),
            className: 'is-header-date',
            centerX: true,
            centerY: true,
            markerFontSize: headerDateFontSize,
            onMove: (x, y) => syncHeaderDateToWire(x, y),
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
            img.dataset.wtceImage = ''
            img.alt = 'Şablon'
            img.draggable = false
            img.className = 'wtce-template-image'
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

    wire.$watch('data.settings.merkez_temperature_font_size', (v) => {
        merkezTempFontSize = num(v, merkezTempFontSize)
        rebuildMarkers()
    })

    wire.$watch('data.settings.header_date_font_size', (v) => {
        headerDateFontSize = num(v, headerDateFontSize)
        rebuildMarkers()
    })

    wire.$watch('data.settings.value_color', () => {
        pullFromWire()
        rebuildMarkers()
    })

    wire.$watch('data.settings.weather_coordinates_json', (v) => {
        if (typeof v !== 'string' || v === '') {
            return
        }

        try {
            const layout = JSON.parse(v)
            if (Array.isArray(layout.weather_slots) && layout.weather_slots.length) {
                weatherSlots = layout.weather_slots
            }
            if (layout.header_date) headerDate = layout.header_date
            rebuildMarkers()
        } catch {
            // ignore invalid json
        }
    })

    window.addEventListener('weather-coordinate-input-updated', (event) => {
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

export default function weatherTemplateCoordinateEditor(wire) {
    return {
        init() {
            initWeatherTemplateCoordinateEditor(this.$el, wire)
        },
    }
}
