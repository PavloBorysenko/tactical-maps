# Observer Map Viewer - Final Fixes

Исправлена ошибка "Observer Map Viewer not initialized" в консоли браузера.

## Проблема

При открытии страницы observer viewer возникала ошибка:

```
hook.js:608 Observer Map Viewer not initialized
```

Карта не загружалась, геообъекты не отображались.

## Причина

В `observerMapViewer.js` отсутствовала **автоматическая инициализация** при загрузке DOM, которая есть в обычном `mapViewer.js`.

## Исправления

### 1. Добавлена автоматическая инициализация

**Файл**: `assets/js/observerMapViewer.js`

```javascript
// Initialize the observer map viewer when the DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Create an instance of ObserverMapViewer only if map container exists
    const mapContainer = document.getElementById('map-container');
    if (mapContainer) {
        const observerMapViewer = new ObserverMapViewer();
    }
});
```

### 2. Добавлено событие ready

**Файл**: `assets/js/observerMapViewer.js`

```javascript
// Generate a user event to notify other scripts
const mapReadyEvent = new CustomEvent('tactical-map-ready', {
    detail: { map: this },
});
document.dispatchEvent(mapReadyEvent);
```

### 3. Исправлен шаблон

**Файл**: `templates/observer_viewer/view.html.twig`

```javascript
// Listen for tactical map ready event
document.addEventListener('tactical-map-ready', function (event) {
    console.log('Tactical map ready event received');
    if (event.detail?.map && event.detail.map.loadGeoObjects) {
        event.detail.map.loadGeoObjects(geoObjects);
    } else {
        console.error('Map instance not found in event');
    }
});
```

## Результат

Теперь `observerMapViewer.js` работает аналогично `mapViewer.js`:

### ✅ **Автоматическая инициализация**

-   При загрузке DOM создается экземпляр `ObserverMapViewer`
-   Карта инициализируется без участия пользователя
-   `window.tacticalMap` устанавливается автоматически

### ✅ **Событийная модель**

-   Испускается событие `tactical-map-ready` когда карта готова
-   Шаблон слушает это событие для загрузки геообъектов
-   Убран `setTimeout` - теперь синхронизация правильная

### ✅ **Консистентность с админской версией**

-   Та же архитектура что и `mapViewer.js`
-   Те же события и паттерны
-   Легко поддерживать и развивать

## Порядок инициализации

1. **DOM загружается** → `DOMContentLoaded` событие
2. **ObserverMapViewer создается** → `new ObserverMapViewer()`
3. **Карта инициализируется** → `init()` вызывается
4. **Устанавливается global** → `window.tacticalMap = this`
5. **Испускается событие** → `tactical-map-ready`
6. **Шаблон получает событие** → загружает геообъекты
7. **Объекты отображаются** → `loadGeoObjects()`

## Debugging информация

Добавлены console.log для отладки:

-   `'Observer Map Viewer initialized'`
-   `'Parsed geo objects: X objects'`
-   `'Tactical map ready event received'`
-   `'Loaded X geo objects for observer'`

## Архитектурная консистентность

Теперь оба файла следуют одному паттерну:

### `mapViewer.js` (для админов)

```javascript
document.addEventListener('DOMContentLoaded', function () {
    const mapViewer = new TacticalMapViewer();
});
```

### `observerMapViewer.js` (для observers)

```javascript
document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.getElementById('map-container');
    if (mapContainer) {
        const observerMapViewer = new ObserverMapViewer();
    }
});
```

## Проверка работы

После исправлений в консоли должны появиться сообщения:

1. `"Observer Map Viewer initialized"`
2. `"Parsed geo objects: N objects"`
3. `"Tactical map ready event received"`
4. `"Loaded N geo objects for observer"`

Если есть ошибки - они будут четко указаны в консоли.
