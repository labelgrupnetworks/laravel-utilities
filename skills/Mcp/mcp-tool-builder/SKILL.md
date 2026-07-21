---
name: mcp-tool-builder
description: Usa esto cuando tengas que crear (o revisar) un nuevo MCP Tool que envuelva un método de Controller (ControllerTool) o un UseCase (UseCaseTool) usando el framework MCP de labelgrup/laravel-utilities — cubre cómo localizar la clase que ya posee el schema de entrada/salida (FormRequest, UseCase, Resource, DTO) y conectarla a la Tool vía los atributos #[Schema]/#[OutputSchema], y cómo registrar la Tool en tu servidor MCP.
---

# Generar un MCP Tool con labelgrup/laravel-utilities

Los MCP Tools construidos con este paquete son wrappers delgados sobre un método de
Controller o un UseCase existentes: `Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\ControllerTool`
resuelve la llamada al controlador (valida el FormRequest, mapea excepciones);
`Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\UseCaseTool` construye el `UseCase`
concreto y ejecuta su `handle()`. Ninguna de las dos escribe `schema()`/`outputSchema()`
a mano: el trait `ResolvesToolSchemas` (ya incluido en ambas clases base) los resuelve
por reflection a partir de los atributos `#[Schema(Clase::class)]` /
`#[OutputSchema(Clase::class, key?, description?)]` declarados en la propia Tool —
`Clase` es siempre la que ya posee (o a la que se le añade) el shape real: el
`FormRequest`/Data class del endpoint, el `UseCase` mismo, o un Resource/DTO/clase
dedicada del proyecto consumidor. Lo único que escribe cada Tool nueva es: qué
endpoint/useCase invoca, y esos dos atributos.

**No hay generador de código todavía** — esta skill es el proceso manual a seguir
hasta que exista.

## Regla de oro

`schema()`/`outputSchema()` (vía las clases que implementan `SchemaInterface`/
`OutputSchemaInterface`) son documentación ejecutable: cada campo debe estar respaldado por
evidencia real en el código (reglas del FormRequest, tipos reales del constructor del UseCase,
`toArray()` de un Resource/adapter externo, un Fake, un Resource/DTO local). **Nunca adivines un
tipo, una nullability ni un modificador (`min`/`max`/`enum`/formato)** — si no puedes
pregunta al usuario.

## Orden de métodos dentro de una clase

Toda clase PHP tocada por esta skill (Tool, FormRequest/Data class, UseCase, clase de
schema dedicada) sigue este orden, sin excepción:

1. `__construct()` — siempre primero, antes incluso que cualquier método estático.
2. Métodos **estáticos**, antes que los de instancia.
3. Dentro de cada grupo (estático / instancia), por visibilidad: `public` → `protected` →
   `private`.
4. Dentro de cada visibilidad, **alfabético** por nombre de método.

Ejemplo de violación real ya corregida: `public static function schema()` declarado
**después** de `public function rules()` en un `FormRequest` — `schema()` es estático,
`rules()` no, así que `schema()` debe ir antes aunque ambos sean `public`. Al añadir o
tocar un método en cualquiera de estas clases, revisa que su posición siga esta regla
antes de dar el cambio por terminado — no solo el contenido del método nuevo.

## Paso 0 — ¿Endpoint o UseCase?

Antes de nada, si no es evidente por la petición del usuario, pregunta explícitamente:
**"¿Esto envuelve un método de Controller existente, o un UseCase (una Action/UseCase
sin Controller de por medio)?"** La respuesta decide la clase base:

- **Endpoint** → `ControllerTool` + `EndpointDTO`. Ver plantilla A en Paso 1.
- **UseCase** → `UseCaseTool` + `UseCaseDTO`. Ver plantilla B en Paso 1. Alternativa
  igual de válida que `ControllerTool` — la eliges cuando la lógica de negocio no
  tiene un Controller/endpoint HTTP natural delante.

Reúne los hechos con `codegraph_explore` (o grep si no hay `.codegraph/`):

### Si es endpoint

1. **Controller + método** que se va a envolver.
2. **FormRequest**, si el método del controller tiene uno (mira la firma del método:
   si recibe un FormRequest tipado como argumento, hay que reflejarlo; si el método no
   recibe argumentos, no hay FormRequest que traducir).
3. **¿El Controller/UseCase llama a un servicio o SDK externo?** Si la respuesta pasa
   por un adapter/transformer antes de llegar al controller, ese adapter —no el
   servicio externo— es la fuente de verdad del Paso 3 (`outputSchema`).
4. **¿Cómo llega el argumento al controller?** Tres variantes, ver Paso 1.

### Si es UseCase

1. **La clase `UseCase` concreta** y su constructor completo — cada parámetro que
   espera, tipo y nullability reales (no hay `rules()` que leer, ver Paso 2).
2. **¿El UseCase ya delega su input a una Data class dedicada** (recibida como único
   argumento de constructor)? Si sí, esa Data class —no el UseCase— es la que
   implementará `SchemaInterface` (de-duplicación, ver Paso 2).
3. **Qué devuelve** el método que ejecuta la lógica (normalmente `handle()`/`perform()`):
   un Resource, un DTO readonly, un array, o una lista paginada. Determina la clase de
   salida del Paso 3.
4. **¿El UseCase llama a un servicio o SDK externo?** Mismo criterio que en el camino
   endpoint — si sí, Paso 3 aplica igual.

## Paso 1 — Esqueleto de la Tool

Busca una Tool existente en un dominio similar para ver el estilo aplicado, y adapta la
plantilla que corresponda (A o B, según Paso 0). El trait `ResolvesToolSchemas` (heredado
por ambas clases base) resuelve `schema()`/`outputSchema()` automáticamente a partir de
los atributos `#[Schema]`/`#[OutputSchema]` — **no declares esos dos métodos en la
Tool** salvo que necesites una válvula de escape (ver Paso 2/3, casos que el atributo no
soporta: lista de N objetos, wrapper no estándar).

### Plantilla A — endpoint (`ControllerTool`)

```php
<?php

namespace App\Mcp\Tools\<Domain>;

use App\Http\Controllers\<Controller>;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\OutputSchema;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\Schema; // omitir si no hay input que declarar, ver Paso 2
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\ControllerTool;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\EndpointDTO;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly; // solo si aplica, ver más abajo

#[Name('domain_action')]                 // snake_case, único en el server
#[Title('Domain: Action')]
#[IsReadOnly]                             // SOLO si el endpoint no tiene efectos secundarios (ver nota)
#[Description('Frase clara en inglés: qué hace + cualquier restricción de negocio/acceso o efecto secundario relevante.')]
#[Schema(<ClassImplementingSchemaInterface>::class)]             // omitir si 0 campos de input, ver Paso 2
#[OutputSchema(<ClassImplementingOutputSchemaInterface>::class)] // + key/description opcionales, ver Paso 3
class <Action>Tool extends ControllerTool
{
    public function endpoint(): EndpointDTO
    {
        return new EndpointDTO(<Controller>::class, '<method>' /* , FormRequest::class | params: ['x'] */);
    }
}
```

### Plantilla B — UseCase (`UseCaseTool`)

```php
<?php

namespace App\Mcp\Tools\<Domain>;

use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\OutputSchema;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\Schema;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts\UseCaseTool;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\UseCaseDTO;
use App\Actions\<Action>UseCase;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly; // solo si aplica

#[Name('domain_action')]
#[Title('Domain: Action')]
#[IsReadOnly]
#[Description('Frase clara en inglés: qué hace + cualquier restricción de negocio/acceso o efecto secundario relevante.')]
#[Schema(<Action>UseCase::class)]                                 // o la Data class dedicada si el UseCase ya delega en una, ver Paso 2
#[OutputSchema(<DedicatedClass>::class)]                          // + key/description/many opcionales, ver Paso 3
class <Action>Tool extends UseCaseTool
{
    public function useCase(Request $request): UseCaseDTO
    {
        return new UseCaseDTO(new <Action>UseCase(
            $request->input('field1'),
            $request->input('field2'),
            // ... resto de parámetros del constructor del UseCase, mismo orden exacto
        ));
    }
}
```

`UseCaseDTO` acepta también, si hace falta, `response_simplified: bool` y
`resource_class: ?string` como 2º/3er argumento — mismo contrato que ya usa
`UseCaseTool::handle()` para llamar `$use_case->handle()->responseToApi(...)`; solo
decláralos si el UseCase concreto los necesita (mira otros usos de la misma familia de
UseCase antes de asumir el default).

### `EndpointDTO` — las tres formas de invocar el controller

`Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\EndpointDTO`:
```php
new EndpointDTO(string $controller, string $method, ?string $request = null, array $params = [], array $models = [])
```

| Firma del método en el Controller | Cómo construir el `EndpointDTO` |
|---|---|
| `method()` sin argumentos | `new EndpointDTO(Controller::class, 'method')` |
| `method(SomeFormRequest $request)` | `new EndpointDTO(Controller::class, 'method', SomeFormRequest::class)` |
| `method(string $param1, ...)` — parámetros de ruta escalares, sin FormRequest | `new EndpointDTO(Controller::class, 'method', params: ['param1', ...])` |
| `method(SomeModel $model)` — route-model-binding, sin FormRequest | `new EndpointDTO(Controller::class, 'method', models: ['schema_field_name' => SomeModel::class])` — resuelto vía `SomeModel::findOrFail($request->get('schema_field_name'))`, imitando el binding implícito real |
| `method(SomeFormRequest $request, string $param1)` — FormRequest **y** parámetro(s) de ruta a la vez | `new EndpointDTO(Controller::class, 'method', SomeFormRequest::class, params: ['param1'])` — `ControllerTool::handle()` valida el FormRequest y lo pasa primero, seguido de los `params`/`models` en el orden declarado |

`params` y `models` se pueden combinar entre sí (se concatenan en el orden en que se
declaran, `params` primero) y con `request` (el FormRequest siempre va primero). No hay
que tocar `ControllerTool` ni `ResolvesToolResponse` para una Tool nueva — ya cubren
estas combinaciones; son abstracciones compartidas por todos los proyectos que consumen
el paquete (si alguna vez crees que necesitas cambiarlas más allá de esto, es código
transversal — ábrelo como cambio en el paquete, no como parche local).

### Atributos

- `#[Name('domain_action')]` — snake_case, `<domain>_<action>`.
- `#[Title('Domain: Action')]` — Title Case.
- `#[IsReadOnly]` — **solo** cuando el endpoint es 100% de lectura, sin efectos
  secundarios. Si el endpoint muta algo aunque sea como side-effect (aunque
  semánticamente parezca un listado o consulta), omite `#[IsReadOnly]` y documenta ese
  efecto secundario en la `#[Description]`.
- `#[Description('...')]` — una frase en inglés, clara, que describa el comportamiento
  real (lee el Controller/Action, no solo el nombre del método) y cualquier
  restricción de negocio o de acceso encontrada en el código.

### `transformResponse()` (opcional)

Solo se sobreescribe cuando hay que reformar el payload antes de exponerlo (por
ejemplo, para descartar campos pesados o innecesarios, o envolver la respuesta en una
clave distinta). Si no hace falta, no la declares — `ResolvesToolResponse` ya devuelve
el payload sin tocar por defecto.

## Paso 2 — Input: localizar la clase dueña y declarar `#[Schema]`

El input **nunca se escribe a mano en la Tool**. Proceso:

1. **Localiza la clase que ya recibe/valida ese input**:
   - Camino endpoint: el `FormRequest` que ya se pasa a `EndpointDTO`.
   - Camino useCase: el propio `UseCase`, o su Data class dedicada si ya delega el input
     ahí (ver Paso 0/decisión de-dup) — nunca las dos a la vez.
   - **Solo params de URL, sin `FormRequest`** (`EndpointDTO` construido con `params: [...]`,
     sin `request` ni `models`): no hay clase natural que posea ese input. Si el shape
     es usado por **una sola Tool**, decláralo `schema()` a mano directamente en esa
     Tool (válvula de escape, no crees una clase solo para esto). Si el mismo shape lo
     **reutilizan ≥2 Tools**, sí crea una clase dedicada — la reutilización real
     justifica la clase, la regla de "no crear clase para params de URL" no aplica
     cuando hay ≥2 consumidores.
   - **`FormRequest` + `params`/`models` a la vez, sin dueño natural único**
     (`EndpointDTO` construido con `request: X::class, params: ['y']`): **`#[Schema]` es
     repetible** (`Attribute::IS_REPEATABLE`) — declara la Tool con **dos (o más)
     atributos `#[Schema(...)]` apilados**, uno por clase, y
     `ResolvesToolSchemas::schema()` hace `array_merge()` de cada `Clase::schema($schema)`
     **en el orden de declaración** (primero el atributo declarado primero). No
     inventes un tercer tipo de clase que combine ambas — cada pieza declara su propio
     trozo:
     - El `param`/`model` reutilizado por **una sola Tool** → sigue la regla de arriba
       (a mano en esa Tool, sin clase dedicada) — pero entonces el `#[Schema]` de esa
       Tool no puede apilarse con el del FormRequest en un único método `schema()` hecho
       a mano, así que igualmente crea una clase dedicada mínima para el param (aunque
       solo la use esta Tool) y apílala — es el único camino para combinar con un
       FormRequest sin escribir `schema()` a mano en la Tool misma.
     - El `param`/`model` reutilizado por **≥2 Tools** → clase dedicada (regla ya
       existente arriba), apilada con el `#[Schema]` del FormRequest.
     - Ejemplo:
       ```php
       #[Schema(IdSchema::class)]
       #[Schema(UpdateRequest::class)]
       class UpdateTool extends ControllerTool
       {
           public function endpoint(): EndpointDTO
           {
               return new EndpointDTO(SomeController::class, 'update', UpdateRequest::class, params: ['id']);
           }
       }
       ```
       El orden de los atributos importa: refleja el orden real de los campos en la
       respuesta esperada.
     - `#[OutputSchema]` **no** es repetible (no hay precedente que lo requiera).
2. **¿Esa clase ya implementa `SchemaInterface::schema(JsonSchema): array`?**
   - **Sí** → en la Tool basta `#[Schema(EsaClase::class)]`. No escribas ningún array
     nuevo.
   - **No** → añádele `implements SchemaInterface` + `schema()` a **esa clase** (nunca a
     la Tool), siguiendo el punto 3.
3. **Cómo escribir `schema()` en la clase dueña**, según el tipo de clase:
   - **`FormRequest`**: cada regla de `rules()` debe tener su equivalente — no solo el
     tipo base, sino cada modificador (`required`, `min`, `max`, `in`, etc.). Usa la
     tabla de traducción de abajo.
   - **`UseCase` (o su Data class dedicada)**: si tu proyecto valida el input de forma
     imperativa (sin un array `rules()` declarativo), no hay reglas que traducir — en su
     lugar, refleja los tipos/nullability **reales** de las propiedades que el
     constructor espera recibir (mismo criterio de evidencia que el resto de esta
     skill: mira el tipo declarado de cada parámetro, no adivines).
4. **Si de verdad no hay input que declarar (0 campos)**, no pongas `#[Schema]` en la
   Tool — el fallback sin atributo ya es `[]` (mismo default que la clase base `Tool`);
   poner un atributo apuntando a una clase que solo devuelve `[]` duplica ese default
   con más código, no menos.

Tabla de traducción regla Laravel → `JsonSchema` (usa el builder que llega en el
parámetro de `schema()`, válida para el camino `FormRequest`):

| Regla Laravel | `JsonSchema` |
|---|---|
| `required` | `->required()` |
| `nullable` (sin `required`) | nada extra — un campo no listado como `->required()` ya es opcional en el input |
| `string` | `$schema->string()` |
| `integer` | `$schema->integer()` |
| `numeric` | `$schema->number()` |
| `boolean` | `$schema->boolean()` |
| `array` (+ reglas `campo.*`) | `$schema->array()->items(...)` — si `campo.*` es un objeto (`campo.*.sub`), usa `$schema->object([...])` dentro de `items()` y aplica `->required()` a las subclaves que en `campo.*.sub` sean `required` |
| `min:N` (string o integer) | `->min(N)` — en `string` es longitud mínima, en `integer`/`number` es valor mínimo |
| `max:N` (string o integer) | `->max(N)` — en `string` es longitud máxima, en `integer`/`number` es valor máximo. Es tan obligatoria de reflejar como `min:N`, no la omitas |
| `in:a,b,c` | `->enum([...])` — si el código ya expone la lista como constante, reutilízala en vez de copiar los valores a mano, así el schema no se desincroniza si la constante cambia |
| `date`, `date_format:...` | `$schema->string()` + `->description('... (formato esperado).')` — documenta el formato en la descripción y deja la validación real de formato al FormRequest; el schema del tool es documentación para el cliente MCP, no una segunda capa de validación |

**Antes de dar por terminado este paso** (camino `FormRequest`), repasa **cada** regla de
`rules()` letra por letra (no solo el tipo base) y confirma que cada modificador (`min`,
`max`, `in`, `required`, etc.) tiene su llamada correspondiente en el schema. Es un error
fácil de cometer quedarse solo con el tipo (`string`, `integer`) y olvidar los
modificadores de longitud/rango — trátalos como obligatorios, al mismo nivel que el tipo.

Para un array de objetos requerido (p.ej. una lista de líneas con un campo obligatorio y
otro opcional por elemento): anida `$schema->object([...])` dentro de `->items()` y marca
`->required()` tanto en el array exterior como en la subclave que en `campo.*.sub` sea
`required`.

**Checklist de este paso**:
- Camino `FormRequest`: cuenta las claves de `rules()` y confirma que `schema()` tiene el
  mismo número de claves de primer nivel (más las anidadas si hay `campo.*.sub`); no debe
  faltar ninguna ni sobrar ninguna inventada. Para cada clave, compara regla por regla
  (no solo el tipo) contra lo que se escribió en el schema.
- Camino `UseCase`: cuenta los parámetros del constructor y confirma que `schema()`
  tiene el mismo número de claves de primer nivel; para cada uno, compara
  tipo/nullability reales.

## Paso 3 — Output: localizar la clase dueña y declarar `#[OutputSchema]`

El output **nunca se escribe a mano en la Tool**. Proceso:

1. **Localiza la clase que ya produce esa forma**: Resource, DTO readonly, o clase de
   schema dedicada (créala si no existe ninguna candidata — sigue la cadena
   Controller/UseCase → Resource/DTO/array de retorno).
   - **Naming de una clase dedicada nueva**: el nombre debe ser **agnóstico a la
     interfaz** que implementa hoy — nunca sufijar "Output"/"Input" solo porque de
     momento solo implementa `OutputSchemaInterface`/`SchemaInterface` (puede acabar
     implementando ambas). El nombre debe reflejar el **campo/concepto real** que
     representa, no una descripción inventada. Un helper de composición que no
     implementa la interfaz (se llama con `::apply()` desde el `shape()` de otra clase)
     **no es una clase de schema** — créalo solo cuando una Tool concreta lo necesite
     (evidencia: el shape ya existe a mano en esa Tool).
2. **¿Esa clase ya implementa `OutputSchemaInterface::shape(JsonSchema): array`?**
   - **Sí** → en la Tool basta `#[OutputSchema(EsaClase::class)]`, con
     `key`/`description`/`many` opcionales:
     - `key` → envuelve el shape bajo esa clave (p.ej.
       `#[OutputSchema(EntityDataSchema::class, 'data', 'Resulting entity after the operation.')]`).
     - `many: true` (requiere `key`) → envuelve el shape como **array de objetos** bajo
       la clave, para respuestas que son una lista de N objetos del mismo shape. La
       misma clase shape sirve objeto único (sin `many`) y array (`many: true`) — la
       cardinalidad la decide el atributo, no la clase. `shape()` devuelve siempre los
       campos de **una** entidad, nunca el envoltorio `['data' => array(...)]`.
   - **No** → añádele `implements OutputSchemaInterface` + `shape()` a **esa clase**
     (nunca a la Tool).
3. **Composición**: el atributo apunta siempre a una única clase. Si el shape final
   necesita envolver otras piezas (paginación, Resources anidados), esa composición vive
   **dentro** del `shape()` de la clase dedicada, que llama a las otras clases con la
   interfaz implementada:
   - Un campo que reutiliza otro tipo con `shape()` propio (otro Resource/DTO/Value
     Object) → `$schema->object(OtraClase::shape($schema))`, nunca reconstruido campo a
     campo.
   - **Paginación**: el atributo **no** necesita ninguna evolución para esto — una clase
     dedicada cuyo `shape()` devuelve el envoltorio completo, usada con `key = null`, ya
     lo expresa (ver punto 4). El paquete incluye
     `Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Traits\PaginationTrait::paginationOutputSchema()`
     para el caso más común (`LengthAwarePaginator` de Eloquent):
     ```php
     public static function paginationOutputSchema(
         JsonSchema $schema,
         ObjectType $item_schema,
         ?string $items_description = null,
         ?string $total_items_description = null
     ): array
     ```
     Devuelve `{items: [...], data: {current_page, last_page, total_items}}`. Si tu
     fuente de datos no es Eloquent (p.ej. una respuesta HTTP externa con su propio
     shape de paginación), no fuerces este trait: compón el objeto a mano en el
     `shape()` de tu clase dedicada, o crea tu propio trait equivalente si varias Tools
     comparten ese shape externo — el patrón (clase dedicada + `key = null`) es el
     mismo, solo cambia de dónde sale el número de páginas/total.
4. **Válvula de escape**: para una **lista de N objetos** del mismo shape usa
   `many: true` (punto 2). Para un **wrapper multi-key compuesto** (paginación, ver
   arriba) usa una clase dedicada cuyo `shape()` devuelve el envoltorio final completo y
   la Tool con `#[OutputSchema(EsaClase::class)]` con `key = null` — **no** es una
   limitación del atributo, es el mismo mecanismo base. El mismo patrón cubre también un
   **escalar-bajo-key** (`{data: "uuid"}`).

### 3.1 — Si el Controller/UseCase NO llama a un servicio externo

Es un endpoint/useCase local. Sigue la cadena Controller/UseCase → Resource/DTO/array de
retorno y construye el `shape()` desde esa fuente (Resource `toArray()`, DTO público, o
el array literal que construye el UseCase). Es responsabilidad del dominio — mira el
tipo declarado de cada propiedad.

### 3.2 — Si el Controller/UseCase SÍ llama a un servicio o SDK externo

**Siempre que el endpoint llame a un servicio externo, hay que consultar el adapter/
transformer de la respuesta (o su Fake/fixture si no hay adapter) antes de escribir el
`outputSchema()`.** No se infiere la forma desde el nombre del campo ni desde la
documentación del servicio externo — se lee el código del adapter/SDK que lo consume.

1. Localiza el adapter o transformer que procesa la respuesta del servicio externo.
2. **¿Hay un adapter que transforma la respuesta?**
   - **Si NO**: la respuesta llega cruda, sin transformar — el schema son los campos
     planos tal como los expone el Fake/fixture correspondiente.
   - **Si SÍ**: lee su `toArray()` (o equivalente) — esas son las claves exactas que
     recibirá la Tool, byte a byte. Si el adapter delega un campo a otro adapter
     (composición interna), **no aplanes los dos niveles en una sola clase de schema**
     — repite este mismo paso para el adapter anidado y compón (ver punto 5).
   - **No te quedes solo en el adapter**: comprueba también si existe alguna capa de
     transformación posterior sobre la respuesta del adapter. Si existe, esa
     transformación final —no el `toArray()` del adapter por sí solo— es la que
     determina la forma real de la respuesta.
3. **Para cada campo, identifica si pasa por algún cast/helper de tipo especial** de tu
   SDK/servicio externo (fechas, valores monetarios, porcentajes, coordenadas,
   imágenes, etiquetas traducibles, etc.) y resuelve su nullability contra el código
   real del cast — nunca por similitud de nombre con otro campo. Si tu proyecto ya tiene
   Value Objects/DTOs que implementan `OutputSchemaInterface` para estos tipos
   compuestos (equivalente a un "NumericType" o "ImageType" propio), reutilízalos con
   `$schema->object(SuTipo::shape($schema))` en vez de reconstruir el objeto campo a
   campo.
4. Si el adapter/UseCase expone paginación, no asumas el wrapping de tipo por defecto en
   los campos de conteo: verifica primero si esos campos concretos pasan por un cast
   real o si son enteros planos leídos directo de la respuesta externa.
5. **Adapter que delega en otro adapter → una clase de schema por adapter, no una
   monolítica.** Cuando el `toArray()` de un adapter llama a otro adapter para un campo,
   replica esa composición en las clases de schema en vez de aplanar todo en un único
   `shape()`: una clase dedicada por adapter, y el `shape()` del adapter padre compone
   la del hijo con `$schema->object(HijoSchema::shape($schema))` (o `->array()->items(...)`
   si el padre expone una colección). Así, si el mismo adapter hijo se reutiliza en otro
   adapter padre distinto, la clase de schema se reutiliza también en vez de duplicarse
   a mano.

## Paso 4 — Registrar la Tool

Añade la clase al array `$tools` de tu servidor MCP (`extends Laravel\Mcp\Server`),
agrupada junto a las demás Tools del mismo dominio (orden alfabético dentro del grupo,
si esa es la convención de tu proyecto). Si necesitas Tools de solo-desarrollo/debug que
nunca deben llegar a producción, regístralas en un servidor MCP separado que tu
aplicación solo active fuera de producción.

## Paso 5 — Verificación final (obligatoria)

```bash
vendor/bin/pint --dirty --format agent
php -l app/Mcp/Tools/<Domain>/<Action>Tool.php
```

Y, si el endpoint modifica datos, prueba contra un entorno/tenant de pruebas dedicado —
nunca contra datos reales, salvo que la tarea especifique otra cosa explícitamente.

## Checklist resumen

- [ ] Decidido endpoint vs UseCase (Paso 0) y clase base correcta (`ControllerTool` vs
      `UseCaseTool`).
- [ ] Identificado Controller+método (o UseCase+constructor) + si llama a un servicio
      externo.
- [ ] `EndpointDTO`/`UseCaseDTO` construido con la variante correcta.
- [ ] Input: localizada la clase dueña (FormRequest/Data/UseCase); si no implementaba
      `SchemaInterface`, añadido ahí (nunca en la Tool); `#[Schema(...)]` declarado en
      la Tool, u omitido si 0 campos. Si el input combina FormRequest + params/models sin
      dueño único, `#[Schema]` apilado (repetible, merge en orden de declaración) — nunca
      `schema()` a mano reimplementando el FormRequest.
- [ ] `schema()` de esa clase cubre el 100% de las reglas del FormRequest, o el 100% de
      los parámetros reales del constructor del UseCase — mismos tipos y **todos** los
      modificadores (`required`, `min`, `max`, `enum`, etc.), no solo el tipo base.
- [ ] Output: localizada la clase dueña (Resource/DTO/clase dedicada); si no implementaba
      `OutputSchemaInterface`, añadido `shape()` ahí (nunca en la Tool);
      `#[OutputSchema(..., key?, description?, many?)]` declarado en la Tool: lista de N
      objetos → `many: true`; wrapper multi-key compuesto (paginación) → clase dedicada
      con `shape()` = envoltorio completo + `key = null`, reutilizando
      `PaginationTrait::paginationOutputSchema()` si la fuente es un `LengthAwarePaginator`
      Eloquent, o tu propio trait equivalente si el origen es externo; escalar-bajo-key →
      mismo patrón `key = null`.
- [ ] `shape()` construido a partir de evidencia real: adapter `toArray()` / capa de
      transformación posterior si existe / Fake/fixture / Resource/DTO local — nunca
      adivinado.
- [ ] Nullability de cada campo externo rastreada hasta el cast/adapter concreto, no
      copiada de otro campo por similitud de nombre.
- [ ] Atributos `#[Name]`/`#[Title]`/`#[Description]` presentes; `#[IsReadOnly]` solo si
      no hay efectos secundarios.
- [ ] Tool registrada en el servidor MCP correspondiente.
- [ ] `vendor/bin/pint --dirty --format agent` y `php -l` pasan limpios.
- [ ] Si toca lógica de negocio no trivial nueva: tests + actualización de documentación
      de API según la convención de tu proyecto.
