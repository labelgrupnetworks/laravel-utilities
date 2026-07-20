---
name: mcp-tool-builder
description: Usa esto cuando tengas que crear (o revisar) un nuevo MCP Tool en app/Mcp/Tools/ que envuelva un método de Controller (ControllerTool/DataObjectControllerTool) o un UseCase (UseCaseTool) existente en NAP Backend — cubre cómo localizar la clase que ya posee el schema de entrada/salida (FormRequest, UseCase, Resource, DTO) y conectarla a la Tool vía los atributos #[Schema]/#[OutputSchema], incluyendo cómo rastrear los adapters/casts del Genesis SDK cuando el endpoint llama a GenesisSDK, y cómo registrar la Tool.
---

# Generar un MCP Tool en NAP Backend

Los MCP Tools de este proyecto son wrappers delgados sobre un método de Controller o
un UseCase existentes: `App\Mcp\Core\Tools\Abstracts\ControllerTool`/`DataObjectControllerTool`
resuelven la llamada al controlador (valida el FormRequest, mapea excepciones); `UseCaseTool`
construye el `UseCase` concreto y ejecuta su `handle()`. Ninguna de las dos escribe
`schema()`/`outputSchema()` a mano: el trait `ResolvesToolSchemas` (ya incluido en las tres
clases base) los resuelve por reflection a partir de los atributos `#[Schema(Clase::class)]` /
`#[OutputSchema(Clase::class, key?, description?)]` declarados en la propia Tool — `Clase` es
siempre la que ya posee (o a la que se le añade) el shape real: el `FormRequest`/Data class del
endpoint, el `UseCase` mismo, o un Resource/DTO/clase dedicada en `app/Mcp/Schemas/{Dominio}/`.
Lo único que escribe cada Tool nueva es: qué endpoint/useCase invoca, y esos dos atributos.

**No hay generador de código todavía** — esta skill es el proceso manual a seguir
hasta que exista.

## Regla de oro

`schema()`/`outputSchema()` (vía las clases que implementan `SchemaInterface`/
`OutputSchemaInterface`) son documentación ejecutable: cada campo debe estar respaldado por
evidencia real en el código (reglas del FormRequest, tipos reales del constructor del UseCase,
`toArray()` de un adapter Genesis, un Fake, un Resource/DTO local). **Nunca adivines un tipo, una
nullability ni un modificador (`min`/`max`/`enum`/formato)** — si no puedes
pregunta al usuario

## Orden de métodos dentro de una clase

Toda clase PHP tocada por esta skill (Tool, FormRequest/Data class, UseCase, clase de
schema dedicada en `app/Mcp/Schemas/`) sigue este orden, sin excepción:

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
**"¿Esto envuelve un método de Controller existente, o un UseCase (`Domain\Nap\*\Actions\*UseCase`)
sin Controller de por medio?"** La respuesta decide la clase base:

- **Endpoint** → `ControllerTool` (o `DataObjectControllerTool` si el endpoint recibe/devuelve
  una `Data` class Spatie en vez de FormRequest/Resource) + `EndpointDTO`. Ver plantilla A en
  Paso 1.
- **UseCase** → `UseCaseTool` + `UseCaseDTO`. Ver plantilla B en Paso 1. Camino menos frecuente
  hoy en el repo (pocos casos reales) — si dudas si el caso encaja, pregunta al usuario antes de
  construir el UseCase a mano.

Reunir los hechos con `codegraph_explore` (o grep si no hay `.codegraph/`):

### Si es endpoint

1. **Controller + método** que se va a envolver.
2. **FormRequest**, si el método del controller tiene uno (mira la firma del método:
   si recibe un FormRequest tipado como argumento, hay que reflejarlo; si el método no
   recibe argumentos, no hay FormRequest que traducir).
3. **¿El Controller/UseCase llama a GenesisSDK?** Busca `GenesisSDK::` o el Resource
   Genesis correspondiente (`Genesis\Resources\*Genesis`) en la cadena Controller →
   Action/UseCase. Esto decide si el Paso 3 (`outputSchema`) pasa por el flujo
   "Genesis" o el flujo "local".
4. **¿Cómo llega el argumento al controller?** Tres variantes, ver Paso 1.

### Si es UseCase

1. **La clase `UseCase` concreta** (`extends CustomUseCase` u otra base de
   `labelgrup/laravel-utilities`) y su constructor completo — cada parámetro que espera,
   tipo y nullability reales (no hay `rules()` que leer, ver Paso 2).
2. **¿El UseCase ya delega su input a una Data class dedicada** (recibida como único argumento
   de constructor, p.ej. `CartCheckOutData` en `CheckoutUseCase`)? Si sí, esa Data class —no el
   UseCase— es la que implementará `SchemaInterface` (de-duplicación, ver Paso 2).
3. **Qué devuelve** el método que ejecuta la lógica (normalmente `handle()`/`perform()`): un
   Resource, un DTO readonly, un array, o una lista paginada. Determina la clase de salida del
   Paso 3.
4. **¿El UseCase llama a GenesisSDK?** Mismo criterio que en el camino endpoint — si sí, Paso 3.2
   aplica igual.

## Paso 1 — Esqueleto de la Tool

Busca una Tool existente en un dominio similar dentro de `app/Mcp/Tools/` para ver el estilo
aplicado, y adapta la plantilla que corresponda (A o B, según Paso 0). El trait
`ResolvesToolSchemas` (heredado por las tres clases base) resuelve `schema()`/`outputSchema()`
automáticamente a partir de los atributos `#[Schema]`/`#[OutputSchema]` — **no declares esos dos
métodos en la Tool** salvo que necesites una válvula de escape (ver Paso 2/3, casos que el
atributo no soporta: lista de N objetos, wrapper no estándar).

### Plantilla A — endpoint (`ControllerTool`/`DataObjectControllerTool`)

```php
<?php

namespace App\Mcp\Tools\<Dominio>;

use App\Http\Controllers\Api\V1\<...>\<Controller>;
use App\Mcp\Core\Schemas\Attributes\OutputSchema;
use App\Mcp\Core\Schemas\Attributes\Schema; // omitir si no hay input que declarar, ver Paso 2
use App\Mcp\Core\Tools\Abstracts\ControllerTool;
use App\Mcp\Core\Tools\DTO\EndpointDTO;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly; // solo si aplica, ver más abajo

#[Name('dominio_accion')]                 // snake_case, único en el server
#[Title('Dominio: Acción')]
#[IsReadOnly]                             // SOLO si el endpoint no tiene efectos secundarios (ver nota)
#[Description('Frase clara en inglés: qué hace + cualquier restricción de negocio/acceso o efecto secundario relevante.')]
#[Schema(<ClaseQueImplementaSchemaInterface>::class)]             // omitir si 0 campos de input, ver Paso 2
#[OutputSchema(<ClaseQueImplementaOutputSchemaInterface>::class)] // + key/description opcionales, ver Paso 3
class <Accion>Tool extends ControllerTool
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

namespace App\Mcp\Tools\<Dominio>;

use App\Mcp\Core\Schemas\Attributes\OutputSchema;
use App\Mcp\Core\Schemas\Attributes\Schema;
use App\Mcp\Core\Tools\Abstracts\UseCaseTool;
use App\Mcp\Core\Tools\DTO\UseCaseDTO;
use Domain\Nap\<Dominio>\Actions\<Accion>UseCase;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly; // solo si aplica

#[Name('dominio_accion')]
#[Title('Dominio: Acción')]
#[IsReadOnly]
#[Description('Frase clara en inglés: qué hace + cualquier restricción de negocio/acceso o efecto secundario relevante.')]
#[Schema(<Accion>UseCase::class)]                                 // o la Data class dedicada si el UseCase ya delega en una, ver Paso 2
#[OutputSchema(<ClaseDedicada>::class)]                           // + key/description/many opcionales, ver Paso 3
class <Accion>Tool extends UseCaseTool
{
    public function useCase(Request $request): UseCaseDTO
    {
        return new UseCaseDTO(new <Accion>UseCase(
            $request->input('campo1'),
            $request->input('campo2'),
            // ... resto de parámetros del constructor del UseCase, mismo orden exacto
        ));
    }
}
```

`UseCaseDTO` (`app/Mcp/Core/Tools/DTO/UseCaseDTO.php`) acepta también, si hace falta,
`response_simplified: bool` y `resource_class: ?string` como 2º/3er argumento — mismo contrato
que ya usa `UseCaseTool::handle()` para llamar `$use_case->handle()->responseToApi(...)`; solo
decláralos si el UseCase concreto los necesita (mira otros usos de la misma familia de UseCase
antes de asumir el default).

### `EndpointDTO` — las tres formas de invocar el controller

`app/Mcp/Core/Tools/DTO/EndpointDTO.php`:
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
declaran, `params` primero) y con `request` (el FormRequest siempre va primero). No
hay que tocar `ControllerTool` ni `ResolvesToolResponse` para una Tool nueva — ya
cubren estas combinaciones; son abstracciones compartidas por todas las Tools (si
alguna vez crees que necesitas cambiarlas más allá de esto, avisa primero al usuario,
es código transversal).

### Atributos

- `#[Name('dominio_accion')]` — snake_case, `<dominio>_<accion>`.
- `#[Title('Dominio: Acción')]` — `Dominio: Acción` en Title Case.
- `#[IsReadOnly]` — **solo** cuando el endpoint es 100% de lectura, sin efectos
  secundarios. Si el endpoint muta algo aunque sea como side-effect (aunque
  semánticamente parezca un listado o consulta), omite `#[IsReadOnly]` y documenta ese
  efecto secundario en la `#[Description]`.
- `#[Description('...')]` — una frase en inglés, clara, que describa el comportamiento
  real (lee el Controller/Action, no solo el nombre del método) y cualquier
  restricción de negocio o de acceso encontrada en el código (organización,
  permisos, etc.).

### `transformResponse()` (opcional)

Solo se sobreescribe cuando hay que reformar el payload antes de exponerlo (por
ejemplo, para descartar campos pesados o innecesarios, o envolver la respuesta en una
clave distinta). Si no hace falta, no la declares — `ResolvesToolResponse` ya devuelve
el payload sin tocar por defecto.

## Paso 2 — Input: localizar la clase dueña y declarar `#[Schema]`

El input **nunca se escribe a mano en la Tool**. Proceso:

1. **Localiza la clase que ya recibe/valida ese input**:
   - Camino endpoint: el `FormRequest` (o `Data` class si es `DataObjectControllerTool`) que
     ya se pasa a `EndpointDTO`.
   - Camino useCase: el propio `UseCase`, o su Data class dedicada si ya delega el input ahí
     (ver Paso 0/decisión de-dup) — nunca las dos a la vez.
   - **Solo params de URL, sin `FormRequest`** (`EndpointDTO` construido con `params: [...]`, sin
     `request` ni `models`): no hay clase natural que posea ese input. Si el shape es usado por
     **una sola Tool**, decláralo `schema()` a mano directamente en esa Tool (válvula de escape,
     no crees una clase solo para esto). Si el mismo shape lo **reutilizan ≥2 Tools** (p.ej.
     `cart_uuid` en `carts_show` y `carts_clear_out`), sí crea una clase dedicada en
     `app/Mcp/Schemas/{Dominio}/` — la reutilización real justifica la clase, la regla de "no
     crear clase para params de URL" no aplica cuando hay ≥2 consumidores.
   - **`FormRequest` + `params`/`models` a la vez, sin dueño natural único** (`EndpointDTO`
     construido con `request: X::class, params: ['y']`): **`#[Schema]` es repetible**
     (`Attribute::IS_REPEATABLE`, `app/Mcp/Core/Schemas/Attributes/Schema.php`) — declara la
     Tool con **dos (o más) atributos `#[Schema(...)]` apilados**, uno por clase, y
     `ResolvesToolSchemas::schema()` hace `array_merge()` de cada `Clase::schema($schema)` **en
     el orden de declaración** (primero el atributo declarado primero). No inventes un tercer
     tipo de clase que combine ambas — cada pieza declara su propio trozo:
     - El `param`/`model` reutilizado por **una sola Tool** → sigue la regla de arriba (a mano en
       esa Tool, sin clase dedicada) — pero entonces el `#[Schema]` de esa Tool no puede
       apilarse con el del FormRequest en un único método `schema()` hecho a mano, así que
       igualmente crea una clase dedicada mínima para el param (aunque solo la use esta Tool) y
       apílala — es el único camino para combinar con un FormRequest sin escribir `schema()` a
       mano en la Tool misma. Si de verdad prefieres no crear esa clase, la alternativa es
       `schema()` a mano en la Tool que reimplemente también los campos del FormRequest — pero
       eso duplica lo que el FormRequest ya declara y se desincroniza si el FormRequest cambia;
       usa el atributo repetible salvo que el usuario pida explícitamente lo contrario.
     - El `param`/`model` reutilizado por **≥2 Tools** → clase dedicada en
       `app/Mcp/Schemas/{Dominio}/` (regla ya existente arriba), apilada con el `#[Schema]` del
       FormRequest.
     - Ejemplo real (`DeliveryExcesses\UpdateTool`, combina `IdDeliveryExcessSchema` — reutilizada
       también por `DestroyTool` — con `UpdateRequest`):
       ```php
       #[Schema(IdDeliveryExcessSchema::class)]
       #[Schema(UpdateRequest::class)]
       class UpdateTool extends ControllerTool
       {
           public function endpoint(): EndpointDTO
           {
               return new EndpointDTO(DeliveryExcessesController::class, 'update', UpdateRequest::class, params: ['id_delivery_excess']);
           }
       }
       ```
       El orden de los atributos importa: refleja el orden real de los campos tal como estaban
       en el `schema()` legacy a mano (aquí: id primero, luego los campos del FormRequest).
     - `#[OutputSchema]` **no** es repetible (no hay precedente que lo requiera) — si algún día
       aparece un caso real que lo necesite, pregunta antes de tocar el atributo.
2. **¿Esa clase ya implementa `SchemaInterface::schema(JsonSchema): array`?**
   (`App\Mcp\Core\Schemas\Interfaces\SchemaInterface`, un único método estático).
   - **Sí** → en la Tool basta `#[Schema(EsaClase::class)]`. No escribas ningún array nuevo.
   - **No** → añádele `implements SchemaInterface` + `schema()` a **esa clase** (nunca a la
     Tool), siguiendo el punto 3.
3. **Cómo escribir `schema()` en la clase dueña**, según el tipo de clase:
   - **`FormRequest`**: cada regla de `rules()` debe tener su equivalente — no solo el tipo
     base, sino cada modificador (`required`, `min`, `max`, `in`, etc.). Usa la tabla de
     traducción de abajo.
   - **`UseCase` (o su Data class dedicada)**: **no hay `rules()` que traducir** — `validate()`
     en los UseCases de este repo es imperativo (`if`s que lanzan `UseCaseException`), no un
     array declarativo. En su lugar, refleja los tipos/nullability **reales** de las
     propiedades que el constructor espera recibir (mismo criterio de evidencia que el resto de
     esta skill: mira el tipo declarado de cada parámetro, no adivines).
4. **Si de verdad no hay input que declarar (0 campos)**, no pongas `#[Schema]` en la Tool — el
   fallback sin atributo ya es `[]` (mismo default que la clase base `Tool`); poner un atributo
   apuntando a una clase que solo devuelve `[]` duplica ese default con más código, no menos.

Tabla de traducción regla Laravel → `JsonSchema` (usa el builder que llega en el
parámetro de `schema()`, válida para el camino `FormRequest`):

| Regla Laravel | `JsonSchema` |
|---|---|
| `required` | `->required()` |
| `nullable` (sin `required`) | nada extra — un campo no listado como `->required()` ya es opcional en el input; no existe un `->nullable()` de input en el uso actual del proyecto |
| `string` | `$schema->string()` |
| `integer` | `$schema->integer()` |
| `numeric` | `$schema->number()` |
| `boolean` | `$schema->boolean()` |
| `array` (+ reglas `campo.*`) | `$schema->array()->items(...)` — si `campo.*` es un objeto (`campo.*.sub`), usa `$schema->object([...])` dentro de `items()` y aplica `->required()` a las subclaves que en `campo.*.sub` sean `required` |
| `min:N` (string o integer) | `->min(N)` — en `string` es longitud mínima, en `integer`/`number` es valor mínimo |
| `max:N` (string o integer) | `->max(N)` — en `string` es longitud máxima, en `integer`/`number` es valor máximo. Es tan obligatoria de reflejar como `min:N`, no la omitas |
| `in:a,b,c` | `->enum([...])` — si el código ya expone la lista como constante, reutilízala en vez de copiar los valores a mano, así el schema no se desincroniza si la constante cambia |
| `date`, `date_format:...` | `$schema->string()` + `->description('... (formato esperado).')` — **decisión consciente de convención**: aunque `StringType` soporta `->pattern()`/`->format()`, la convención actual del repo es documentar el formato solo en la descripción y dejar la validación real de formato al FormRequest (`ControllerTool` la ejecuta al invocar el endpoint) — el schema del tool es documentación para el cliente MCP, no una segunda capa de validación. No introduzcas `->pattern()` a menos que el usuario pida explícitamente cambiar esta convención en todas las Tools |

**Antes de dar por terminado este paso** (camino `FormRequest`), repasa **cada** regla de
`rules()` letra por letra (no solo el tipo base) y confirma que cada modificador (`min`, `max`,
`in`, `required`, etc.) tiene su llamada correspondiente en el schema. Es un error fácil de
cometer quedarse solo con el tipo (`string`, `integer`) y olvidar los modificadores de
longitud/rango — trátalos como obligatorios, al mismo nivel que el tipo.

Para un array de objetos requerido (p.ej. una lista de líneas con un campo obligatorio
y otro opcional por elemento): anida `$schema->object([...])` dentro de `->items()` y
marca `->required()` tanto en el array exterior como en la subclave que en
`campo.*.sub` sea `required`.

**Checklist de este paso**:
- Camino `FormRequest`: cuenta las claves de `rules()` y confirma que `schema()` tiene el mismo
  número de claves de primer nivel (más las anidadas si hay `campo.*.sub`); no debe faltar
  ninguna ni sobrar ninguna inventada. Para cada clave, compara regla por regla (no solo el
  tipo) contra lo que se escribió en el schema.
- Camino `UseCase`: cuenta los parámetros del constructor y confirma que `schema()` tiene el
  mismo número de claves de primer nivel; para cada uno, compara tipo/nullability reales
  (no reglas — no las hay).

## Paso 3 — Output: localizar la clase dueña y declarar `#[OutputSchema]`

El output **nunca se escribe a mano en la Tool**. Proceso:

1. **Localiza la clase que ya produce esa forma**: Resource, DTO readonly, o clase de schema
   dedicada en `app/Mcp/Schemas/{Dominio}/` (créala si no existe ninguna candidata — sigue la
   cadena Controller/UseCase → Resource/DTO/array de retorno).
   - **Naming de una clase dedicada nueva en `app/Mcp/Schemas/{Dominio}/`**: el nombre debe ser
     **agnóstico a la interfaz** que implementa hoy — nunca sufijar "Output"/"Input" solo porque
     de momento solo implementa `OutputSchemaInterface`/`SchemaInterface` (puede acabar
     implementando ambas). El nombre debe reflejar el **campo/concepto real** que representa
     (p.ej. el parámetro `cart_uuid` → `CartUuidSchema`, no una paráfrasis genérica como
     `UuidSchema`) — no una descripción inventada. Si ese nombre real no repite el dominio de la
     carpeta contenedora, no lo añadas de más (`Carts/ListSchema`, no `Carts/CartListOutputSchema`,
     porque "list" no es un campo con nombre propio, es la operación — el dominio ya lo da la
     carpeta). Precedente ya en el repo: `app/Mcp/Schemas/Vaccines/LaboratoriesSchema.php`.
     Un helper de composición que no implementa la interfaz (se llama con `::apply()` desde el
     `shape()` de otra clase) **no es una clase de schema** y no debe vivir en
     `app/Mcp/Schemas/{Dominio}/` ni crearse por adelantado sin un caller real — créalo solo
     cuando una Tool concreta lo necesite (evidencia: el shape ya existe a mano en esa Tool).
2. **¿Esa clase ya implementa `OutputSchemaInterface::shape(JsonSchema): array`?**
   (`App\Mcp\Core\Schemas\Interfaces\OutputSchemaInterface`).
   - **Sí** → en la Tool basta `#[OutputSchema(EsaClase::class)]`, con `key`/`description`/`many`
     opcionales:
     - `key` → envuelve el shape bajo esa clave (p.ej.
       `#[OutputSchema(CartDataSchema::class, 'data', 'Resulting cart after the operation.')]`).
     - `many: true` (requiere `key`) → envuelve el shape como **array de objetos** bajo la clave,
       para respuestas que son una lista de N objetos del mismo shape (p.ej.
       `#[OutputSchema(CartDataSchema::class, 'data', 'Every open cart...', many: true)]` en
       `Carts/ItemsMoveTool`). La misma clase shape sirve objeto único (`ShowTool`) y array
       (`ItemsMoveTool`) — la cardinalidad la decide el atributo, no la clase. `shape()` devuelve
       siempre los campos de **una** entidad, nunca el envoltorio `['data' => array(...)]`.
   - **No** → añádele `implements OutputSchemaInterface` + `shape()` a **esa clase** (nunca a la
     Tool), siguiendo 3.1/3.2 según corresponda.
3. **Composición**: el atributo apunta siempre a una única clase. Si el shape final necesita
   envolver otras piezas (paginación, Resources anidados), esa composición vive **dentro** del
   `shape()` de la clase dedicada, que llama a las otras clases con la interfaz implementada:
   - Un campo que reutiliza otro tipo con `shape()` propio (`NumericType`, `LabelTranslateType`,
     `ImageType`, otro Resource/DTO) → `$schema->object(OtraClase::shape($schema))`, nunca
     reconstruido campo a campo (ver 3.2 para los casts de Genesis).
   - **Paginación — dos shapes reales, cada uno con su trait ya verificado**: el atributo
     **no** necesita ninguna evolución para esto — una clase dedicada cuyo `shape()` devuelve
     el envoltorio de 2 claves completo, usada con `key = null`, ya lo expresa (ver punto 4).
     Dos familias confirmadas contra código real, ambas como métodos del mismo trait
     `PaginationTrait` (`app/Mcp/Core/Schemas/Traits/PaginationTrait.php`):
     - **Laravel/Eloquent** (`LengthAwarePaginator` + `ApiResponse::parsePagination()` /
       `ResponseJsonHelper::parsePagination()`): `{items: [...], data: {current_page, last_page,
       total_items}}`, enteros planos. Método: `PaginationTrait::paginationOutputSchema($schema,
       $item_object_type, items_description?, total_items_description?)`. Caso real verificado:
       `app/Mcp/Schemas/Manufacturers/ListSchema.php` + `Manufacturers/ListTool`.
     - **Genesis/API externa** (respuesta HTTP externa parseada a mano, no Eloquent): shape
       distinto por dominio (no asumas nombres de clave — verifícalos, p.ej. `Restricted` usa
       `{products: [...], pagination: {total_pages, total_items}}`, **sin** `current_page`/
       `per_page` pese a que el input sí los reciba — compáralo con la respuesta real, no con el
       input). Método para el sub-objeto de conteos:
       `PaginationTrait::paginationGenesisOutputSchema($schema, total_items_description?)` →
       `{total_items, total_pages}`, **enteros planos** (no uses `NumericType::shape()` aquí salvo
       que confirmes que esos campos concretos pasan por un cast Genesis real — la wrapping con
       `NumericType` se probó y no correspondía a ningún caller real). Caso real verificado:
       `app/Mcp/Schemas/Restricted/ListSchema.php` + `Restricted/ListTool`.
     Antes de reutilizar cualquiera de los dos traits en una Tool nueva, compara su shape real
     (fuente: el array final que arma el Controller/UseCase, no el helper que lo generó) contra
     el trait — si no coincide exactamente, no lo fuerces: ajusta el trait (si el desfase es
     genérico) o compón el objeto a mano en el `shape()` de esa clase dedicada.
4. **Válvula de escape**: para una **lista de N objetos** del mismo shape usa `many: true` (punto
   2). Para un **wrapper multi-key compuesto** (paginación, ver arriba) usa una clase dedicada
   cuyo `shape()` devuelve el envoltorio final completo y la Tool con `#[OutputSchema(EsaClase::class)]`
   con `key = null` — **no** es una limitación del atributo, es el mismo mecanismo base. Solo
   queda como escape valve real el **escalar-bajo-key** (`{data: "uuid"}`, ver
   `Carts/DirectPurchaseSchema`), que también usa `key = null` por el mismo motivo.

### 3.1 — Si el Controller/UseCase NO llama a GenesisSDK

Es un endpoint/useCase local. Sigue la cadena Controller/UseCase → Resource/DTO/array de
retorno y construye el `shape()` desde esa fuente (Resource `toArray()`, DTO público, o el
array literal que construye el UseCase). No hay tabla de casts que rastrear aquí — es
responsabilidad del dominio, mira el tipo declarado de cada propiedad.

### 3.2 — Si el Controller/UseCase SÍ llama a GenesisSDK — proceso obligatorio

**Siempre que el endpoint llame a Genesis a través del SDK, hay que consultar el
adapter de la respuesta (o el Fake si no hay adapter) antes de escribir el
`outputSchema()`.** No se infiere la forma desde el nombre del campo ni desde la
documentación de Genesis — se lee el código del SDK.

1. Localiza el método del Resource Genesis que se llama
   (`vendor/labelgrup/genesis-sdk/src/Resources/<X>Genesis.php`).
2. **¿El método encadena `->useAdapter(SomeAdapter::class)`?**
   - **Si NO**: la respuesta llega cruda al controller, sin transformar — confírmalo
     en `Genesis\Core\BaseGenesis::requestToGenesis()` (rama sin `$this->adapter`). La
     fuente de verdad es el fixture del Faker correspondiente
     (`vendor/labelgrup/genesis-sdk/src/Fakers/<X>GenesisFake.php`, constante
     `RESPONSE_<METODO>`). El schema son campos planos, tipos escalares, sin el
     wrapper `{value, type, is_sensitive_data}`.
   - **Si SÍ**: abre esa clase Adapter en `vendor/labelgrup/genesis-sdk/src/Adapters/`
     y lee su `toArray()` (y `mapGenesisKeys()`/`attributes()` si existen) — esas son
     las claves exactas que recibirá la Tool, byte a byte. Si el adapter delega un
     campo a otro Adapter (`OtroAdapter::make(...)`/`::collection(...)`), **no
     aplanes los dos niveles en una sola clase de schema** — repite este mismo paso
     para el adapter anidado y compón, ver punto 5.
   - **No te quedes solo en el adapter**: comprueba también si existe alguna capa de
     transformación posterior sobre la respuesta del adapter (por ejemplo, un mapeo de
     campos concretos a otro tipo/objeto antes de llegar al controller). Si existe,
     esa transformación final —no el `toArray()` del adapter por sí solo— es la que
     determina la forma real de la respuesta.
3. **Para cada campo de `toArray()`, identifica si pasa por un helper `parse*()` de
   `Genesis\Adapters\GenesisAdapter`** y resuelve su nullability con esta tabla (ya
   verificada contra el código concreto del SDK, no adivinada):

   | Helper en el adapter | Config `genesis.php:casts` | Clase cast concreta (`src/Nap/Shared/DTO/Casts/Genesis/`) | Forma en el schema | ¿Nullable? |
   |---|---|---|---|---|
   | `parseDatetime($v, $origin_format, $tz)` | `datetime` | `Datetime::cast(): ?string` (SIEMPRE devuelve ISO 8601 vía `toIso8601String()`, nunca el `$origin_format` de entrada) | `$schema->string()` | **Sí**, si el valor de entrada puede venir vacío → `null` |
   | `parseQuantity($v, $sensible)` | `quantity` | `Quantity::cast()` → `NumericType::quantity()` | `$schema->object(NumericType::shape($schema))` — objeto `{value, type, is_sensitive_data}` | `value` **NUNCA null** (constructor hace `(float)$origin_value`) |
   | `parseCurrency($v, $sensible)` | `currency` | `Currency::cast()` → `NumericType::currency()` | `$schema->object(NumericType::shape($schema))` — mismo objeto `{value, type, is_sensitive_data}` | `value` **NUNCA null** (misma coerción) |
   | `parsePercentage($v, ..., $sensible)` | `percentage` | `Percentage::cast()` → `NumericType::percentage()` | `$schema->object(NumericType::shape($schema))` — mismo objeto `{value, type, is_sensitive_data}` | `value` **NUNCA null** (misma coerción) |
   | `parseGeoPosition($lat, $lon, $sensible)` | `geo_position` | `GeoPosition::cast()` → `GeoPositionType{type, latitude: CoordinateType, longitude: CoordinateType}` | objeto `{type, latitude: {type, value, is_sensitive_data}, longitude: {...}}` — sin clase propia; cada coordenada es un `$schema->object(NumericType::shape($schema))` anidado | `value` de cada coordenada **SÍ es nullable** (sin coerción) |
   | `parseImage($src, $alt)` | `image` | `Image::cast()` → `ImageType{type, src, alt}` | `$schema->object(ImageType::shape($schema))` (`src\Nap\Shared\DTO\Casts\ImageType`) | `src`/`alt` **SÍ son nullable** |

   `NumericType::shape()`, `ImageType::shape()` y `LabelTranslateType::shape()` viven en
   `src/Nap/Shared/DTO/Casts/` e implementan `OutputSchemaInterface` — son la fuente de verdad
   para estos tres shapes, **este es el patrón a seguir en Tools nuevas** (no el trait viejo
   `BuildsCommonSchemas`/`$this->numericSchema()`, que sigue vivo solo para las Tools no
   migradas al patrón de atributos — no lo uses en código nuevo). **Nunca repitas a mano el
   objeto `{value, type, is_sensitive_data}`**: usa siempre `NumericType::shape($schema)` para
   que los distintos consumidores no diverjan si el shape cambia.

   Si en el `toArray()` aparece un campo con forma `{value, label, type, prefix, suffix}`
   (típico de estados/resoluciones traducibles, convención `%%____%%`), usa
   `$schema->object(LabelTranslateType::shape($schema))` en vez de reconstruir el objeto campo
   a campo.

   Si un campo del `toArray()` NO pasa por ninguno de estos helpers (es un valor plano
   tipo `$data['campo']` o `$this->something`), no asumas nullability — solo márcalo
   `->nullable()` si tienes evidencia concreta (el valor puede faltar en el Faker/en la
   respuesta real, o el propio código hace `?? null`).

4. Si el adapter/UseCase expone paginación, no asumas el wrapper `NumericType` por defecto:
   verifica primero si esos campos concretos pasan por un cast Genesis real (tabla de arriba) o
   si son enteros planos leídos directo de la respuesta HTTP (caso confirmado en `Restricted`).
   Para el sub-objeto de conteos usa `PaginationTrait::paginationGenesisOutputSchema($schema,
   '<descripción de total_items>')` (`app/Mcp/Core/Schemas/Traits/PaginationTrait.php`) —
   ver "Paginación" en el punto 3 de arriba para el criterio completo.
5. **Adapter que delega en otro Adapter → una clase de schema por Adapter, no una
   monolítica.** Cuando `toArray()` de un Adapter llama a otro Adapter para un campo
   (reutilización dentro del propio SDK), replica esa composición en las clases de
   schema en vez de aplanar todo en un único `shape()`: una clase dedicada por
   Adapter (misma regla de naming del punto 1 de arriba, reflejando el Adapter real —
   p.ej. `ProductInfoSchema` para `ProductInfoAdapter`), y el `shape()` del Adapter
   padre compone la del hijo con `$schema->object(HijoSchema::shape($schema))` (o
   `->array()->items(...)` si el padre usa `::collection()`), igual que ya se hace
   con `NumericType`/`ImageType`/`LabelTranslateType`. Así, si el mismo Adapter hijo
   se reutiliza en otro Adapter padre distinto, la clase de schema se reutiliza
   también en vez de duplicarse a mano. Caso real de 3 niveles en el SDK (aún sin
   Tool que lo consuma, pero así se debe tratar el día que aparezca uno):
   `ProductAdapter::toArray()` (`vendor/labelgrup/genesis-sdk/src/Adapters/Products/ProductAdapter.php:20-23`)
   delega `product_info` a `ProductInfoAdapter::make(...)`, que a su vez delega
   `stock_details` a `ProductStockDetailsAdapter::collection(...)` — serían
   `ProductSchema` → compone `ProductInfoSchema` → compone `ProductStockDetailsSchema`,
   tres clases, no una.

## Paso 4 — Registrar la Tool

Añade la clase al array `$tools` de `app/Mcp/Servers/ApServer.php`, agrupada junto a
las demás Tools del mismo dominio (orden alfabético dentro del grupo, como ya está).

## Paso 5 — Verificación final (obligatoria)

```bash
vendor/bin/pint --dirty --format agent
php -l app/Mcp/Tools/<Dominio>/<Accion>Tool.php
```

Y, si el endpoint modifica datos, recuerda la convención del proyecto: pruébalo contra
la pharmacy `S77776`, nunca contra datos reales, salvo que la tarea especifique otra
explícitamente.

## Limpieza de fragmentos huérfanos / traits legacy

Si al construir o revisar una Tool te encuentras con `use BuildsCartDataSchema;` (o
cualquier referencia a `App\Mcp\Core\Tools\Concerns\BuildsCartDataSchema`): ese trait
tiene **0 callers reales** en el repo (confirmado por grep) — elimina la referencia y,
si al hacerlo el fichero del trait se queda sin ningún consumidor en todo `app/Mcp`,
bórralo entero (no lo dejes `@deprecated`, ya es código muerto). **No toques**
`App\Mcp\Core\Tools\Concerns\BuildsCommonSchemas`: sigue siendo infraestructura activa
para las Tools todavía no migradas al patrón de atributos (`$this->numericSchema()` /
`$this->translateSchema()` / `$this->paginationSchema()`) — se retira solo cuando no
quede ninguna Tool usándolo, no antes.

## Checklist resumen

- [ ] Decidido endpoint vs UseCase (Paso 0) y clase base correcta
      (`ControllerTool`/`DataObjectControllerTool` vs `UseCaseTool`).
- [ ] Identificado Controller+método (o UseCase+constructor) + si usa GenesisSDK.
- [ ] `EndpointDTO`/`UseCaseDTO` construido con la variante correcta.
- [ ] Input: localizada la clase dueña (FormRequest/Data/UseCase); si no implementaba
      `SchemaInterface`, añadido ahí (nunca en la Tool); `#[Schema(...)]` declarado en
      la Tool, u omitido si 0 campos. Si el input combina FormRequest + params/models sin
      dueño único, `#[Schema]` apilado (repetible, merge en orden de declaración) — nunca
      `schema()` a mano reimplementando el FormRequest.
- [ ] `schema()` de esa clase cubre el 100% de las reglas del FormRequest, o el
      100% de los parámetros reales del constructor del UseCase — mismos tipos y
      **todos** los modificadores (`required`, `min`, `max`, `enum`, etc.), no solo
      el tipo base.
- [ ] Output: localizada la clase dueña (Resource/DTO/clase dedicada); si no
      implementaba `OutputSchemaInterface`, añadido `shape()` ahí (nunca en la Tool);
      `#[OutputSchema(..., key?, description?, many?)]` declarado en la Tool: lista de N
      objetos → `many: true`; wrapper multi-key compuesto (paginación) → clase dedicada con
      `shape()` = envoltorio completo + `key = null`, reutilizando `PaginationTrait::paginationOutputSchema()`
      (Laravel/Eloquent) o `PaginationTrait::paginationGenesisOutputSchema()` (Genesis/API externa)
      según corresponda; escalar-bajo-key → mismo patrón `key = null` (única válvula de escape real).
- [ ] Si se crea una clase dedicada nueva en `app/Mcp/Schemas/{Dominio}/`: nombre
      agnóstico a la interfaz (sin sufijo "Output"/"Input") y sin repetir el dominio
      ya implícito en la carpeta contenedora.
- [ ] `shape()` construido a partir de evidencia real: adapter `toArray()` / capa de
      transformación posterior si existe / Faker `RESPONSE_*` / Resource/DTO local —
      nunca adivinado.
- [ ] Nullability de cada campo Genesis rastreada hasta la clase cast concreta
      (tabla del Paso 3.2), no copiada de otro campo por similitud de nombre.
- [ ] Campos con forma `{value, type, is_sensitive_data}`, `{value, label, type, prefix,
      suffix}` o el wrapper de paginación reutilizan `NumericType::shape()` /
      `LabelTranslateType::shape()` / `PaginationTrait::paginationOutputSchema()` /
      `PaginationTrait::paginationGenesisOutputSchema()`, no objetos reconstruidos a mano ni el
      trait legacy `BuildsCommonSchemas` en código nuevo.
- [ ] Atributos `#[Name]`/`#[Title]`/`#[Description]` presentes; `#[IsReadOnly]` solo
      si no hay efectos secundarios.
- [ ] Tool registrada en `ApServer::$tools`.
- [ ] `vendor/bin/pint --dirty --format agent` y `php -l` pasan limpios.
- [ ] Si toca lógica de negocio no trivial nueva (no es el caso típico de un wrapper
      de Tool, pero si aplica): tests + actualización de Swagger según CLAUDE.md.
