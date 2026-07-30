---
name: mcp-server-version-guard
description: Usa esto cuando quieras comprobar si un cambio reciente en las Tools de un servidor MCP (construido con labelgrup/laravel-utilities) requiere subir la versión declarada en su atributo #[Version(...)], y con qué tipo de bump (MAJOR/MINOR/PATCH). Regenera el snapshot con `mcp:tools:schema-snapshot`, diffea contra el snapshot trackeado en git, clasifica cada cambio con una tabla de reglas, y propone (nunca aplica) el nuevo valor de #[Version]. Cuando la clasificación de un cambio sea ambigua, se detiene y pregunta al desarrollador mostrando su propia recomendación — nunca autoclasifica en silencio.
---

# Vigilar la versión de un servidor MCP

Un cliente externo que consume un servidor MCP (`extends Laravel\Mcp\Server`) necesita
saber cuándo su integración se ha quedado desactualizada respecto a las Tools que
expone ese servidor. La convención es semver en el atributo `#[Version('x.y.z')]` del
Server. Esta skill automatiza la detección de cambios y la clasificación del bump,
pero **nunca edita el atributo por sí sola** — siempre propone el diff exacto y espera
confirmación.

## Requisito previo

El proyecto debe tener configurado `laravel-utilities.mcp.servers` (mapa
`slug => ['class' => Server::class, 'schema_snapshot_path' => '...']` — la ruta del
snapshot es por servidor, no compartida) — es lo que le dice a `mcp:tools:schema-snapshot`
qué servidores snapshotar y dónde escribir cada uno. Si el proyecto quiere enriquecer
el catálogo con metadatos propios (agrupación, scopes de autorización, etc.), lo hace
en su propia capa extendiendo
`Labelgrup\LaravelUtilities\AI\Mcp\Resources\Abstracts\AbstractToolsSchemaCatalogResource`
y sobrescribiendo `decorateEntry()` — esta skill trabaja siempre sobre el snapshot
genérico del paquete, no sobre esa capa de enriquecimiento del proyecto.

## Proceso

Aplícalo **de forma independiente por servidor** — un cambio en un servidor nunca
afecta a la versión de otro.

1. Ejecuta `php artisan mcp:tools:schema-snapshot` (sin argumento, regenera todos los
   servidores configurados, cada uno en su propio `schema_snapshot_path`).
2. Para cada servidor configurado, `git diff --stat -- <su schema_snapshot_path>` para
   ver si su snapshot cambió (rutas distintas por servidor — lee
   `config('laravel-utilities.mcp.servers')` para no asumir rutas). Si no hay diff en
   ninguno, informa que no hace falta bump y termina aquí; si solo cambió el de un
   servidor, el resto no necesita bump.
3. Para cada snapshot con diff, recorre el árbol JSON-Schema **recursivamente** (no
   solo el nivel superior — un cambio puede estar anidado dentro de un `array().items()`
   u objeto interno) y clasifica cada cambio detectado con esta tabla:

   | Cambio detectado | Clasificación |
   |---|---|
   | Tool eliminada | MAJOR |
   | Tool nueva | MINOR |
   | Nuevo campo **requerido** en inputSchema | MAJOR |
   | Nuevo campo **opcional** en inputSchema/outputSchema | MINOR |
   | Campo eliminado de inputSchema/outputSchema | MAJOR |
   | Campo pasa de requerido a opcional (se relaja) | MINOR/PATCH |
   | Cambio de tipo en un campo existente (p. ej. `integer`→`string`) | **Ambiguo — pregunta siempre al desarrollador con tu recomendación por defecto MAJOR**, ya que un diff de JSON-Schema no distingue "corregí un bug de documentación" de "cambié el comportamiento real" |
   | Enum pierde valores, o un valor se renombra (quitar+añadir a la vez) | MAJOR |
   | Enum gana valores | MINOR |
   | Tool gana `readOnlyHint` (amplía acceso a un scope de solo lectura) | MINOR |
   | Tool pierde `readOnlyHint` (restringe acceso) | MAJOR |
   | Solo cambia `description`/`title` | PATCH |
   | Posible rename de tool (un `name` desaparece y otro aparece a la vez) | Señálalo como sospechoso de rename y pregunta — estructuralmente indistinguible de "eliminar + añadir" en el JSON |

4. **Regla explícita, no solo para el caso de cambio de tipo**: cuando la
   clasificación de cualquier cambio no sea clara con certeza, la skill se detiene y
   pregunta al desarrollador, mostrando su propia recomendación y el razonamiento —
   nunca autoclasifica en silencio.
5. El bump final de cada servidor es el **más alto** entre todos sus cambios
   clasificados (un MAJOR en cualquier Tool obliga a MAJOR en el servidor entero,
   aunque el resto de cambios sean PATCH).
6. Propón (muestra el diff exacto del atributo, nunca lo apliques ni lo commitees) el
   nuevo valor de `#[Version('x.y.z')]` en el Server afectado.

## Checklist resumen

- [ ] `laravel-utilities.mcp.servers` configurado — si no lo está, avisa y detente.
- [ ] `mcp:tools:schema-snapshot` ejecutado y `git diff --stat` revisado en el
      `schema_snapshot_path` propio de cada servidor.
- [ ] Cada cambio recorrido recursivamente (no solo el nivel superior del schema) y
      clasificado contra la tabla.
- [ ] Cualquier clasificación ambigua (tipo cambiado, posible rename, o cualquier otra
      no cubierta con certeza por la tabla) preguntada al desarrollador con
      recomendación explícita — nunca decidida en silencio.
- [ ] Bump final = el más alto de los cambios detectados en ese servidor.
- [ ] Propuesta de `#[Version]` mostrada como diff — nunca aplicada ni commiteada por
      la skill.
