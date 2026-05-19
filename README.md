# Arma Reforger MILSIM Management Plugin

Plugin nativo para WordPress diseñado para clanes de Arma Reforger. Facilita la gestión de misiones, eventos, estructuras de combate (ORBAT), reserva de slots, pasadores de medallas y geolocalización táctica.

---

## Características Principales
- **Gestión de Misiones y Eventos**: Tipos de contenido personalizados con soporte para autores y resúmenes.
- **Motor de ORBAT Interactivo**: Visualización táctica o modo de reserva por tarjetas con validación de requisitos de medallas y prevención de duplicados.
- **Pasador de Medallas (Ribbon Rack)**: Muestra las metopas militares obtenidas por cada operador ordenadas por jerarquía.
- **Geolocalizador Táctico**: Muestra las coordenadas de red y la ubicación del visitante con estética militar.
- **Herramientas de Servidor**: Lanzador y generador de presets JSON integrado con la API de Pterodactyl y avisos a Telegram.

---

## Documentación de Shortcodes

El plugin registra los siguientes shortcodes que pueden ser insertados en páginas, entradas, widgets o maquetadores visuales (como Elementor):

### 1. Geolocalización y Coordenadas
#### `[coordenadas_militar]`
Muestra un bloque táctico con la IP del visitante, su localización aproximada, latitud, longitud y un indicador parpadeante de inteligencia.
- **Atributos**:
  - `color` (por defecto: `#849b4c`): Color de fuente en formato Hexadecimal.
  - `size` (por defecto: `14px`): Tamaño de la tipografía (ej. `16px`, `1.2em`).
  - `layout` (por defecto: `vertical`): Orientación de las líneas (`vertical` u `horizontal`).
  - `ip` (por defecto: `0`): Mostrar la dirección IP del visitante (`1` para activar).
  - `intel` (por defecto: `1`): Mostrar el prefijo parpadeante `[INTEL]_` (`1` para activar).
  - `location` (por defecto: `0`): Mostrar la ciudad y el código de país (ej. `MADRID_ES`) (`1` para activar).
- **Ejemplo**:
  ```html
  [coordenadas_militar color="#00ff00" layout="horizontal" ip="1" location="1"]
  ```

---

### 2. Condecoraciones y Medallas
#### `[clan_pasador_medallas]`
Renderiza el pasador de diario (Ribbon Rack) de un operador mostrando sus medallas ordenadas por prioridad visual (jerarquía).
- **Atributos**:
  - `user_id` (por defecto: ID del usuario logueado): ID de WordPress del usuario. Si no se especifica, se muestra el pasador del usuario que visualiza la página.
- **Ejemplo**:
  ```html
  [clan_pasador_medallas user_id="5"]
  ```

---

### 3. Calendario y Fechas
#### `[clan_calendario]`
Muestra un calendario interactivo táctico integrado con FullCalendar V6. Carga de forma asíncrona todos los eventos de partidas publicados. En móviles se adapta automáticamente a vista de lista, y en ordenadores a vista mensual.
- **Atributos**: Ninguno.
- **Ejemplo**:
  ```html
  [clan_calendario]
  ```

#### `[fecha_evento]`
Imprime la fecha y hora de inicio de la misión/evento actual de forma escrita en español.
- **Atributos**: Ninguno (se autodetecta el ID del post en el loop).
- **Ejemplo**:
  *Salida: "Viernes, 15 de Mayo a las 20:00"*
  ```html
  [fecha_evento]
  ```

---

### 4. Estructura de Combate (ORBAT)
#### `[clan_orbat]`
Shortcode de conveniencia (legacy) que combina el diagrama ORBAT y la lista de Addons requeridos debajo.
- **Atributos**: Mismo soporte de atributos que `[rmm_orbat]`.
- **Ejemplo**:
  ```html
  [clan_orbat]
  ```

#### `[rmm_orbat]`
Muestra la estructura de escuadras y frecuencias del evento o misión actual.
- **Atributos**:
  - `mode` (por defecto: detectado automáticamente según tipo de post): 
    - `milsim`: Diagrama táctico de solo lectura diseñado para la vista de misiones maestro.
    - `cards`: Grid interactivo de tarjetas donde los usuarios logueados pueden apuntarse, desapuntarse y ver qué slots requieren condecoraciones específicas.
- **Ejemplo**:
  ```html
  [rmm_orbat mode="cards"]
  ```

#### `[rmm_addons_list]`
Muestra una sección colapsable (`<details>`) con la lista completa de addons requeridos para la misión (importados de la Steam Workshop).
- **Atributos**: Ninguno.
- **Ejemplo**:
  ```html
  [rmm_addons_list]
  ```

---

### 5. Metadatos de Misión (Frontend Customization)
Diseñados para construir plantillas personalizadas en Elementor o maquetadores similares.

#### `[rmm_title]`
Muestra el título limpio de la misión, sin las alteraciones del filtro de fecha del evento.
- **Ejemplo**: `[rmm_title]`

#### `[rmm_author]`
Muestra el autor definido para la misión.
- **Ejemplo**: `[rmm_author]`

#### `[rmm_summary]`
Muestra el resumen de la misión dentro de un contenedor estructurado.
- **Ejemplo**: `[rmm_summary]`

#### `[rmm_description]`
Muestra la descripción detallada del escenario de la misión.
- **Ejemplo**: `[rmm_description]`

#### `[rmm_workshop_url]`
Muestra un botón/enlace apuntando a la página de Steam Workshop de la misión/mod original.
- **Atributos**:
  - `text` (por defecto: `"Ver en Steam Workshop"`): Texto dentro del botón.
  - `class` (por defecto: `"rmm-workshop-btn button elementor-button"`): Clases CSS aplicadas al botón.
- **Ejemplo**:
  ```html
  [rmm_workshop_url text="Descargar Escenario" class="mi-boton-personalizado"]
  ```

---

### 6. Galería de Misiones
#### `[rmm_missions_grid]`
Muestra una galería interactiva con todas las misiones creadas. Incluye un buscador/filtro en tiempo real que permite filtrar misiones por autor, número mínimo de slots, número máximo de addons, y si contienen o no los paquetes tácticos ACE y RHS.
- **Atributos**:
  - `posts_per_page` (por defecto: `8`): Número de misiones cargadas por página.
- **Ejemplo**:
  ```html
  [rmm_missions_grid posts_per_page="12"]
  ```
