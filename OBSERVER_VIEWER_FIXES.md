# Observer Viewer Filters Fix

Исправлены проблемы с отображением фильтров на странице observer viewer.

## Проблема

На странице карты Observer отображались фильтры (виджеты для скрытия/показа объектов по сторонам и TTL), которые должны быть доступны только администраторам. Observer должен видеть все активные объекты без возможности фильтрации.

## Исправления

### 1. Отключение фильтров для Observer Mode

**Файл**: `assets/js/mapGeoObjects.js`

Добавлена проверка `observer mode` в метод `createSidesLegend()`:

```javascript
createSidesLegend(objects) {
    // Remove existing legend
    this.removeSidesLegend();

    // Don't create filters for observer mode
    if (this.map.observerMode) {
        return;
    }

    // ... остальной код фильтров
}
```

### 2. Правильная инициализация Observer Mode

**Файл**: `templates/observer_viewer/view.html.twig`

Изменен JavaScript код для правильной работы с observer mode:

```javascript
// Set observer mode - this prevents filters from being created
window.tacticalMap.observerMode = true;

// Load geo objects data directly (don't use API call for observer)
window.tacticalMap.geoObjectManager.clearGeoObjects();
window.tacticalMap.geoObjectManager.allObjects = geoObjects;
window.tacticalMap.geoObjectManager.renderGeoObjects(geoObjects);
```

### 3. Исправление структуры данных

**Файл**: `templates/observer_viewer/view.html.twig`

Приведена в соответствие структура JSON данных с тем что ожидает JavaScript:

```json
{
    "id": obj.id,
    "hash": obj.hash,
    "title": obj.name,           // было: "name": obj.name
    "description": obj.description,
    "type": obj.geometryType,    // было: "geometryType": obj.geometryType
    "geoJson": obj.geometry,     // было: "geometry": obj.geometry
    "ttl": obj.ttl,
    "iconUrl": obj.iconUrl,
    "side": obj.side ? { ... } : null,
    "sideId": obj.side ? obj.side.id : null,
    "isExpired": obj.isExpired,
    "remainingTtl": obj.remainingTtl,
    "createdAt": obj.createdAt ? obj.createdAt.format('Y-m-d H:i:s') : null,
    "updatedAt": obj.updatedAt ? obj.updatedAt.format('Y-m-d H:i:s') : null
}
```

## Результат

Теперь на странице observer viewer:

✅ **Фильтры не отображаются** - observer не может скрывать/показывать объекты  
✅ **Все активные объекты видны** - observer видит все объекты с актуальным TTL  
✅ **Панель инструментов работает** - observer может пользоваться картой (zoom, layers, etc.)  
✅ **Правильная загрузка данных** - объекты загружаются напрямую из контроллера, не через API

## Для администраторов

На административных страницах (`/admin/maps/{id}`) фильтры продолжают работать как прежде:

-   ✅ Фильтр по сторонам (Sides)
-   ✅ Фильтр "Show only active objects" (TTL)
-   ✅ Легенда с чекбоксами

## Техническая информация

-   **Observer mode** определяется наличием `data-observer-mode="true"` в контейнере карты
-   **Фильтры создаются** только если `this.map.observerMode !== true`
-   **Данные передаются** напрямую через JSON скрипт, а не через API вызовы
-   **Активные объекты** фильтруются на уровне контроллера через `GeoObjectRepository::findActiveByMap()`

## Маршруты

-   **Observer View**: `/observer/{access_token}` - без фильтров
-   **Admin Map View**: `/admin/maps/{id}` - с фильтрами
