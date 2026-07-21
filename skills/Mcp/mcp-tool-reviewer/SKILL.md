---
name: mcp-tool-reviewer
description: Usa esto cuando tengas que revisar uno o varios MCP Tools ya existentes (construidos con labelgrup/laravel-utilities) para comprobar si un cambio en el Controller/endpoint o el UseCase que envuelven (según la Tool) ha desincronizado su schema()/outputSchema() declarado — y actualizarlo. Localiza la clase dueña real (FormRequest, UseCase, Resource, DTO), compara campo a campo, y corrige el schema/shape de esa clase (nunca inventa la Tool desde cero, eso es mcp-tool-builder). Tres modos: por diff, dirigido a una Tool concreta, o auditoría completa del servidor MCP.
---

# Revisar y mantener sincronizados los MCP Tools

Esta skill **no genera Tools nuevas** (eso es `mcp-tool-builder`) — vigila las que ya
existen. Su propósito central: un endpoint o un UseCase cambia con el tiempo (nuevo
parámetro, campo añadido/quitado, tipo distinto) y el `schema()`/`outputSchema()` de la
Tool que lo envuelve puede quedarse desactualizado sin que nadie se entere hasta que
falla en producción. Esta skill detecta ese desfase y lo corrige.

## Regla de oro

La comparación siempre va **contra el código real de hoy**, no contra lo que la Tool
ya declaraba ni contra lo que parece razonable por el nombre del campo: lee el
Controller/FormRequest o el UseCase (input) y el Resource/DTO/adapter externo
(output) con `codegraph_explore`/`Read`, y compara campo a campo contra el
`schema()`/`shape()` actual. **Nunca reportes "sin cambios" ni "desactualizado" sin
haber leído ambos lados.** Cita siempre `fichero:línea` de la Tool/clase de schema
**y** de la clase dueña real. **Nunca toques código sin antes imprimir el listado
completo de hallazgos** en la conversación; si el alcance pedido es ambiguo (p.ej.
"revisa las tools de un dominio" sin decir cuáles), pregunta antes de actuar. **No
persistas ningún fichero de checklist ni informe en el repo** — el resultado vive en
la conversación; si detecta un desfase y toca corregirlo, el fix se aplica al código
real (clase dueña o, en Tools legacy, la propia Tool), no a un documento aparte.

## Orden de métodos dentro de una clase

Al tocar cualquier clase (Tool, FormRequest/Data class, UseCase, clase de schema
dedicada), revisa también que el orden de sus métodos cumple:

1. `__construct()` primero, siempre.
2. Estáticos antes que de instancia.
3. Dentro de cada grupo, por visibilidad: `public` → `protected` → `private`.
4. Dentro de cada visibilidad, alfabético.

Ejemplo de violación: `public static function schema()` colocado después de
`public function rules()` — al ser estático, `schema()` va antes aunque ambos sean
`public`. Si al corregir un desfase de schema tocas un método y su clase no cumple
este orden, corrígelo en el mismo pase (no hace falta pedir confirmación aparte, es
mecánico).

## Dónde vive el schema hoy (para saber qué tocar si hay desfase)

Antes de corregir, localiza dónde vive el `schema()`/`shape()` de esa Tool concreta —
no todas las Tools de un proyecto maduro están necesariamente en el mismo patrón (ver
`mcp-tool-builder` para el proceso recomendado):

- **Vía atributo** (patrón recomendado): la Tool lleva `#[Schema(Clase::class)]` y/o
  `#[OutputSchema(Clase::class, key?, description?, many?)]`; el `schema()`/`shape()`
  real vive en `Clase` (FormRequest/Data/UseCase/Resource/DTO/clase dedicada),
  resuelto por `Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolSchemas`
  (paquete). **El fix va en `Clase`, nunca en la Tool.** `#[Schema]` es **repetible**:
  una Tool puede llevar `#[Schema(A::class)]` + `#[Schema(B::class)]` apilados cuando
  el input combina un FormRequest con un param/model sin dueño natural único
  (`ResolvesToolSchemas::schema()` hace `array_merge()` de cada `Clase::schema($schema)`
  en orden de declaración). Al revisar una Tool así, compara **cada** clase apilada
  contra su propia fuente (el param contra la firma real del Controller, el
  FormRequest contra sus `rules()`) — no asumas que basta con revisar una y
  extrapolar a la otra, y confirma que el orden de los atributos sigue reflejando el
  orden real de los campos si la Tool ya tenía un orden establecido. `#[OutputSchema]`
  **no** es repetible (no hay precedente que lo requiera). `many: true` (requiere
  `key`) envuelve el `shape()` como array de objetos bajo la clave — la misma `Clase`
  sirve objeto único (sin `many`) y colección (`many: true`); al revisar, comprueba
  que la cardinalidad del atributo (`many` sí/no) coincide con si la respuesta real es
  una lista o un objeto único. Paginación compuesta (`{items:[...], data:{...}}` o un
  wrapper multi-key equivalente) tampoco es limitación del atributo: `key = null` +
  `shape()` de 2 keys basta. Si la Tool usa
  `Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Traits\PaginationTrait::paginationOutputSchema()`
  (Eloquent `LengthAwarePaginator`: `items`+`data{current_page,last_page,total_items}`),
  verifica que la fuente real sigue siendo un paginador Eloquent — si el proyecto tiene
  su propio trait equivalente para otra fuente de paginación (una respuesta HTTP
  externa con su propio shape), confirma que no se ha mezclado con `PaginationTrait`
  en el mismo schema: nunca deberían aparecer mezclados ambos wrappers de paginación.
- **A mano en la Tool** (patrón legacy, si aún queda alguna Tool sin migrar): la Tool
  define `public function schema(JsonSchema $schema): array` / `outputSchema(...)` con
  el array construido dentro de sí misma, sin apoyarse en los atributos. **El fix va en
  la propia Tool** — no es obligatorio migrarla al patrón de atributos en el mismo pase
  (indícalo como oportunidad, pero corrige el desfase real primero; migrar es una tarea
  aparte que el usuario puede pedir explícitamente). Si encuentras alguna Tool
  referenciando un trait de composición de schemas que ya no existe en el repo, es
  código muerto de una refactorización anterior — señálalo como hallazgo aparte, no lo
  des por hecho como patrón legacy válido.

## Paso 0 — ¿Qué modo?

Si no es evidente por la petición del usuario, pregunta explícitamente:
**"¿Quieres que revise por diff (algo que acaba de cambiar), una Tool concreta, o
una auditoría completa de las Tools registradas?"**

- **Por diff** → Paso 1.
- **Dirigido** (una Tool concreta) → Paso 2.
- **Auditoría completa** (todo el array de Tools del servidor MCP) → Paso 3.

Reunir los hechos siempre con `codegraph_explore` (o grep si no hay `.codegraph/`),
igual que en `mcp-tool-builder` — no abras ficheros sueltos a ciegas.

## Paso 1 — Por diff

1. **Identifica qué cambió**: `git diff --stat` sobre Controllers, FormRequests,
   UseCases, o Resources/DTOs de salida.
2. **Localiza las Tools afectadas**:
   - Si el Controller/método cambió: grep del Controller dentro de `endpoint():
     EndpointDTO` en el directorio de Tools de tu proyecto.
   - Si cambió una clase referenciada por atributo: grep sobre
     `#[Schema(NombreClase::class)]` / `#[OutputSchema(NombreClase::class` en ese
     mismo directorio.
   - Si cambió un UseCase: grep sobre `new NombreUseCase(` dentro de
     `useCase(Request): UseCaseDTO`.
3. **Para cada Tool encontrada**, trae Tool + clase dueña real en una sola llamada
   (`codegraph_explore "<NombreTool> <NombreClase/Controller/UseCase>"`) y compara
   campo a campo el `schema()`/`shape()` vigente contra la firma/reglas/constructor
   actuales: qué se añadió, qué se quitó, qué cambió de tipo/nullability/modificador.
4. **Si hay desfase**: corrígelo en el sitio correcto (ver "Dónde vive el schema
   hoy") siguiendo el mismo proceso de `mcp-tool-builder` (Paso 2 para input, Paso 3
   para output, incluido el proceso de rastreo de adapters/casts externos si aplica) —
   nunca adivines el nuevo campo, repite la misma evidencia que exigiría construirlo
   desde cero.
5. **Si no hay desfase**: dilo explícitamente, **"sin cambios"**, con qué se comparó.

## Paso 2 — Dirigido

Este modo cubre una, varias, o incluso todas las Tools **cuando el usuario las
identifica explícitamente** (por nombre, dominio, Controller, UseCase, o diciendo
"todas" / "revísalas todas") — si el usuario no lista nada y solo pide "una auditoría"
sin acotar, es el Paso 3 (auditoría completa) el que aplica, no este.

0. **Si no se puede deducir por el contexto de la conversación qué Tool(s)/endpoint(s)/
   UseCase(s) revisar** (el usuario no dio nombre de Tool, ruta, Controller, UseCase,
   dominio, ni dijo "todas", y no hay ningún candidato obvio en lo que se habló
   antes): **pregunta explícitamente antes de buscar nada** — "¿Qué Tool(s) quieres
   que revise (nombre `#[Name]`, Controller/método, UseCase, un dominio entero, o
   todas)?". No adivines por similitud ni elijas "la más probable".
1. **Localiza cada Tool del alcance dado** (`codegraph_search`/grep por
   `#[Name('...')]`, nombre de clase, o filtrando por dominio/directorio si el alcance
   es "todo un dominio" o similar) y determina para cada una si envuelve un endpoint o
   un UseCase (mira si extiende `ControllerTool` o `UseCaseTool`).
2. **Para cada Tool, localiza su clase dueña real de hoy**:
   - Endpoint: el Controller+método vigente (vía `EndpointDTO`) y su FormRequest (si
     tiene) — lee la firma del método y `rules()` actuales.
   - UseCase: la clase `UseCase` vigente y su constructor actual (o su Data class
     dedicada si delega el input ahí).
   - Output (ambos caminos): el Resource/DTO/clase de schema dedicada que produce la
     respuesta hoy — sigue la cadena hasta el `toArray()`/array de retorno real,
     incluido el proceso de rastreo de adapters/casts del Paso 3.2 de `mcp-tool-builder`
     si el Controller/UseCase llama a un servicio externo. Si ese adapter delega un
     campo a otro adapter, comprueba que la clase de schema replica esa composición
     (una clase por adapter, compuestas entre sí) en vez de una única clase monolítica
     aplanando los dos niveles — si encuentras una clase monolítica cubriendo un
     adapter padre + hijo, es un hallazgo a reportar aunque los campos coincidan uno a
     uno (afecta a reutilización, no a corrección puntual). Ver Paso 3.2 punto 5 de
     `mcp-tool-builder`.
3. **Para cada Tool del alcance, localiza dónde vive su `schema()`/`shape()`
   declarado hoy** (ver sección de arriba) y compara campo a campo contra el paso 2:
   mismo número de campos, mismos tipos, mismos modificadores/nullability.
4. **Si hay desfase en alguna**: corrígelo en el sitio correcto, mismo proceso que
   Paso 1.4 — una Tool a la vez, nunca arreglos a medias.
5. **Reporta el resultado de todas las Tools del alcance**, no solo las que tengan
   desfase. Si el alcance es una única Tool, basta con `fichero:línea` concretos de
   ambos lados o **"sin cambios"** explícito. Si el alcance cubre varias o "todas",
   usa la misma tabla que Paso 3 (`Tool | Estado | Detalle`) para que el conjunto se
   lea de un vistazo, y confirma con el usuario antes de aplicar fixes en más de una
   Tool a la vez.

## Paso 3 — Auditoría completa

1. Lee el array de Tools de tu servidor MCP (`extends Laravel\Mcp\Server`) — fuente
   única de verdad de qué Tools están activas en producción. No incluye Tools de
   solo-desarrollo/debug registradas en un servidor separado no-producción, si tu
   proyecto tiene uno — si el usuario pide auditar también esas, trátalas como
   alcance aparte explícito.
2. Aplica el Paso 2 a cada una — para las que no han cambiado su Controller/UseCase/
   Resource desde la última revisión, basta confirmar rápido que la cuenta de campos
   sigue cuadrando; profundiza (lectura campo a campo con `codegraph_explore`) en las
   que sí muestran indicios de cambio reciente (`git log` sobre la clase dueña) o en
   las que el usuario señale.
3. De paso, anota qué Tools siguen en patrón legacy (`schema()`/`outputSchema()` a
   mano en la propia Tool, ver sección de arriba) — dato útil para priorizar
   migración oportunista, pero **no es el hallazgo principal de este modo**: el
   hallazgo principal es desfase real detectado.
4. **Reporta como tabla** en la conversación (Tool → sin cambios / desfase detectado →
   detalle) — **no la guardes en fichero**. Si el usuario quiere que se corrijan
   varias a la vez, confirma cuáles antes de tocar código (no asumas "todas").

Formato de tabla sugerido:

| Tool | Estado | Detalle |
|---|---|---|
| `orders_show` | sin cambios | `OrderSchema::shape()` cubre 100% de `OrderData`/`OrderItemData` actuales |
| `catalog_manufacturers` | desfase detectado | `ManufacturerSchema::shape()` no declara `country`, presente en `ManufacturerAdapter::toArray()` |
| `legacy_list` | legacy, sin desfase | `outputSchema()` a mano, pero sigue cubriendo el Resource actual |

## Verificación tras aplicar un fix

Mismo cierre que `mcp-tool-builder`:

```bash
vendor/bin/pint --dirty --format agent
php -l <fichero tocado>
```

Y, si la Tool corregida modifica datos, pruébala contra un entorno/tenant de pruebas
dedicado, nunca contra datos reales, salvo que la tarea especifique otra cosa
explícitamente.

## Checklist resumen

- [ ] Decidido el modo (Paso 0): por diff / dirigido / auditoría completa.
- [ ] Para cada Tool revisada: identificado endpoint vs UseCase, y la clase dueña real
      de hoy (Controller+FormRequest; UseCase+constructor; Resource/DTO/adapter externo
      para el output).
- [ ] Localizado dónde vive el `schema()`/`shape()` declarado (clase vía atributo, o
      hardcoded en la Tool) antes de corregir nada.
- [ ] Comparación campo a campo hecha con evidencia citada `fichero:línea` de ambos
      lados — ningún hallazgo basado en suposición o similitud de nombre.
- [ ] Si hay desfase: corregido en el sitio correcto, con el mismo rigor de
      `mcp-tool-builder` (tipos, modificadores, nullability, rastreo de adapters/casts
      externos si aplica) — no solo el campo que faltaba, todo el shape
      re-verificado.
- [ ] Reporte impreso en la conversación **antes** de tocar cualquier código.
- [ ] Ningún fichero de checklist/informe persistido en el repo.
- [ ] Tras cualquier fix: `vendor/bin/pint --dirty --format agent` y `php -l`
      limpios; entorno/tenant de pruebas si la Tool modifica datos.
