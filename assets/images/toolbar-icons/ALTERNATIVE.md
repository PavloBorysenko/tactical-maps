# Альтернативный способ - через IMG теги

Если вам нужен больший контроль над иконками, можете изменить код в `mapToolbar.js`:

## Заменить создание кнопки центрирования:

```javascript
createCenterButton() {
    const centerBtn = L.DomUtil.create(
        'button',
        'toolbar-btn center-btn',
        this.toolbar
    );

    // Создаём img элемент
    const icon = document.createElement('img');
    icon.src = '/build/images/toolbar-icons/center.svg';
    icon.alt = 'Center';
    icon.style.width = '20px';
    icon.style.height = '20px';

    centerBtn.appendChild(icon);
    centerBtn.title = 'Center Map';
    centerBtn.type = 'button';

    L.DomEvent.on(centerBtn, 'click', (e) => {
        L.DomEvent.stopPropagation(e);
        this.centerMap();
    });
}
```

## Заменить создание переключателя координат:

```javascript
createCoordinatesToggle() {
    const coordWrapper = L.DomUtil.create(
        'div',
        'toolbar-toggle-wrapper',
        this.toolbar
    );

    const coordCheckbox = L.DomUtil.create(
        'input',
        'toolbar-checkbox',
        coordWrapper
    );
    coordCheckbox.type = 'checkbox';
    coordCheckbox.id = 'coord-toggle';

    const coordLabel = L.DomUtil.create(
        'label',
        'toolbar-label',
        coordWrapper
    );
    coordLabel.htmlFor = 'coord-toggle';

    // Создаём img элемент
    const icon = document.createElement('img');
    icon.src = '/build/images/toolbar-icons/coordinates.svg';
    icon.alt = 'Coordinates';
    icon.style.width = '18px';
    icon.style.height = '18px';

    coordLabel.appendChild(icon);
    coordLabel.title = 'Coordinates Mode';

    L.DomEvent.on(coordCheckbox, 'change', (e) => {
        L.DomEvent.stopPropagation(e);
        this.toggleCoordinatesMode(e.target.checked);
    });
}
```

## Преимущества IMG подхода:

-   Более точный контроль размера
-   Легче отладка (иконка всегда видна в инспекторе)
-   Возможность добавить атрибуты alt для доступности
-   Проще динамическое изменение src

## Недостатки:

-   Больше кода
-   Менее гибкое стилизирование через CSS
-   Сложнее сделать hover эффекты с заменой иконки
