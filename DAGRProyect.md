# 🗺️ DAGR Project — Mapa Tactico en la Web

**Autor:** [=TFR=] Task Force Recon  
**Fecha:** Mayo 2026  
**Estado:** En pausa — documentacion y planificacion

---

## Objetivo

Mostrar un mapa tactico de Arma Reforger (Everon, Arland, etc.) en la web del clan usando LeafletJS, con posiciones de jugadores en tiempo real enviadas por el addon ORBAT Link.

---

## Recursos existentes

### Repositorio: EnfusionMapMaker

Herramienta que automatiza:
1. Captura de screenshots del mapa en el World Editor de Arma Reforger
2. Procesado en tiles para LeafletJS (distintos niveles de zoom)
3. Extraccion de coordenadas de objetos del mapa a JSON

### Archivos clave del repo

| Archivo | Funcion |
|---|---|
| `Web/leaflet/` | Libreria LeafletJS |
| `Web/leafletPlugins/` | Plugins (MarkerCluster, etc.) |
| `Web/reforger-map.js` | Motor del mapa: CRS personalizado, conversion de coordenadas, tiles |
| `Web/everon/` | Tiles del mapa de Everon (LOD 0-5) |
| `Web/arland/` | Tiles del mapa de Arland (LOD 0-5) |

---

## Como funciona reforger-map.js

- CRS personalizado: 1 unidad de juego = 1/12.501 pixeles
- `gameCoordsToLatLng([x, y])` -> posicion en el mapa Leaflet (con offset de 50)
- `addMapMarkers(map, [[x1,y1], [x2,y2], ...])` -> pinta marcadores
- TileLayer con Y invertido y zoom reverso (basado en LOD)

---

## Plan de implementacion

### Fase 1 — Shortcode basico `[rmm_tactical_map]`
- Copiar `leaflet/` y `reforger-map.js` a `assets/` del plugin
- Subir tiles de mapa a `wp-content/uploads/maps/{mapname}/`
- Shortcode con parametros: `map="everon"`, `zoom="3"`, `height="600px"`

### Fase 2 — Endpoint REST para posiciones
- Ruta POST `/wp-json/clan/v1/map/positions`
- Recibir array de jugadores con coordenadas
- Almacenar en transient de 30s

### Fase 3 — Actualizacion en tiempo real
- JS hace polling cada 5s al endpoint GET
- Marcadores con icono de faccion, nombre al hover, rotacion segun heading

---

## Pendiente del addon ORBAT Link

Campos nuevos necesarios en el payload de telemetria:

| Campo | Tipo | Descripcion |
|---|---|---|
| `pos_x` | float | Coordenada X en el mundo |
| `pos_y` | float | Coordenada Y en el mundo |
| `pos_z` | float | Altura (opcional) |
| `heading` | float | Direccion de la mira en grados |
| `map` | string | Nombre del mapa: "everon", "arland" |

---

## Tareas pendientes

- [ ] Descargar tiles de Everon/Arland del repo
- [ ] Subir tiles a `wp-content/uploads/maps/`
- [ ] Copiar `leaflet/` y `reforger-map.js` al plugin
- [ ] Crear `class-tactical-map-handler.php`
- [ ] Implementar shortcode `[rmm_tactical_map]`
- [ ] Endpoint REST `clan/v1/map/positions`
- [ ] Polling JS para actualizacion de posiciones
- [ ] Coordinar con el dev del addon los nuevos campos
- [ ] Documentar en el manual de shortcodes
