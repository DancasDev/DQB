# DQB - Dynamic Query Builder

DQB es una librería de PHP diseñada para simplificar la construcción de consultas SQL dinámicas y complejas. Permite a los desarrolladores definir esquemas de datos y construir consultas de manera modular y flexible, facilitando la creación de APIs RESTful, sistemas de informes y aplicaciones web con interfaces de usuario dinámicas.

## Problemas que resuelve

* **Complejidad de consultas dinámicas:** DQB simplifica la construcción de consultas SQL basadas en parámetros variables, evitando la concatenación manual de cadenas y reduciendo el riesgo de errores y vulnerabilidades de seguridad.
* **Mantenimiento de código:** Al centralizar la lógica de construcción de consultas en un esquema de datos, DQB facilita el mantenimiento y la actualización del código.
* **Rendimiento:** DQB incluye un sistema de caché para almacenar la configuración del esquema, mejorando el rendimiento. Además, al construir el SQL basándose únicamente en los campos, filtros, orden y paginación solicitados, se generan consultas más cortas y eficientes, evitando joins innecesarios y optimizando la ejecución en la base de datos.

## Características principales

* **Definición de esquemas de datos:** Permite definir esquemas de datos para tablas y campos, incluyendo relaciones y niveles de acceso.
* **Procesadores modulares:** Incluye procesadores para campos, filtros, orden y paginación, permitiendo la construcción de consultas complejas de manera modular.
* **Soporte para joins:** Permite definir relaciones entre tablas y generar cláusulas JOIN automáticamente.
* **Sistema de caché:** Almacena la configuración del esquema en caché para mejorar el rendimiento.
* **Manejo de excepciones:** Utiliza excepciones personalizadas para facilitar la depuración y el manejo de errores.
* **Flexibilidad:** Permite la configuración de la conexión a la base de datos y la personalización de la construcción de consultas.

## Casos de uso

DQB es ideal para:

* **APIs RESTful complejas:** Construye APIs que permiten a los clientes filtrar, ordenar y paginar grandes conjuntos de datos de manera flexible.
* **Sistemas de informes y análisis:** Genera informes complejos a partir de una base de datos, aplicando filtros y agregaciones a los datos.
* **Aplicaciones web con interfaces de usuario dinámicas:** Traduce las interacciones del usuario en consultas SQL eficientes.
* **Microservicios que acceden a bases de datos:** Centraliza la lógica de acceso a datos y simplifica la construcción de consultas en cada microservicio.
* **Sistemas de gestión de contenidos (CMS):** Construye sistemas CMS que permiten a los usuarios buscar y filtrar contenido de manera flexible.


## Instalación:

Utilice Composer para descargar GAC:

```bash
composer require dancasdev/dqb
```

**Requerimientos:**

- PHP 8 o superior
- Extensión PDO (habilitada por defecto en la mayoría de las instalaciones de PHP)
- Extensión JSON (habilitada por defecto en la mayoría de las instalaciones de PHP)

## Uso Básico

### Ejemplo de uso simple

Este ejemplo muestra cómo utilizar DQB para construir una consulta simple que selecciona todos los campos de la tabla `users` donde el campo `status` es igual a `active`.

```php
<?php

use DancasDev\DQB\DQB;
use DancasDev\DQB\Schema;
use PDO;

// Configuración del esquema
$schema = new Schema([
    'users' => [] // tabla principal
], [
    'id' => [],
    'name' => [],
    'email' => [],
    'age' => [],
    'city' => [],
    'status' => [],
]);

// Conexión a la base de datos
$connection = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'password');

// Instancia de DQB
$dqb = new DQB($schema);
$dqb->setConnection($connection);

// Preparación y ejecución de la consulta
$results = $dqb->prepare(
    fields: '*',
    filters: ['status','active']
)->find();

// Impresión de los resultados
print_r($results);
```
### Explicación del esquema

El esquema de la base de datos se define utilizando la clase `Schema`. El esquema describe las tablas y campos que se utilizarán en las consultas, así como sus relaciones y configuraciones.

#### Definición de tablas

Las tablas se definen como un array asociativo, donde las claves son los nombres de las tablas (o alias) y los valores son arrays de configuración.

**Keys de configuración de tablas permitidos:**

* `is_primary` (bool): Indica si es la tabla primaria (se calcula automáticamente).
* `is_extra` (bool): Indica si se trata de una tabla cuyos campos se obtendrán de una subconsulta (false por defecto, solo aplicable a tablas secundarias). Funcionalidad aún no implementada, posiblemente mediante callbacks.
* `dependency` (string|array): Campo(s) de dependencia hacia otra tabla (requerido para tablas secundarias).
* `name` (string): Nombre real de la tabla (si está vacío, se usa la clave de configuración).
* `alias` (string): Alias de la tabla (calculado automáticamente).
* `sql` (string): Declaración SQL de la tabla (calculada automáticamente).
* `sql_concat` (string): Concatenación de nombre o alias de la tabla (calculada automáticamente).
* `access_level` (int): Nivel de acceso para los campos de la tabla (0 por defecto).
* `read_disabled` (bool): Inhabilita la lectura de los campos de la tabla (false por defecto).
* `filter_disabled` (bool): Inhabilita el filtrado de los campos de la tabla (false por defecto).
* `order_disabled` (bool): Inhabilita el ordenamiento de los campos de la tabla (false por defecto).
* `join` (array): Configuración de la unión con otra tabla (requerido para tablas secundarias no extra).
    * `on` (string): Condición de la unión.
    * `type` (string): Tipo de unión ('INNER', 'LEFT', 'RIGHT', 'OUTER', 'LEFT OUTER', 'RIGHT OUTER', 'INNER' por defecto).

**Ejemplo de definición de tablas:**

```php
[
    'users' => [],
    'posts' => [
        'dependency' => 'id',
        'join' => ['on' => 'users.id = posts.user_id']
    ],
    'posts_details' => [
        'dependency' => 'post_id',
        'is_extra' => true
    ]
]
```

#### Definición de campos

Los campos se definen como un array asociativo, donde las claves son los nombres de los campos (o alias) y los valores son arrays de configuración

**Keys de configuración de campos permitidos:**

* `table` (string): Tabla a la que pertenece el campo (clave de la tabla, por defecto la tabla primaria).
* `name` (string): Nombre real del campo (si está vacío, se usa la clave de configuración).
* `alias` (string): Alias del campo (calculado automáticamente).
* `sql` (string): Declaración SQL del campo (calculada automáticamente).
* `sql_select` (string): Declaración SQL del campo en el "SELECT" (nombre o alias, calculado automáticamente).
* `access_level` (int): Nivel de acceso del campo (hereda de la tabla si está vacío).
* `access_denied` (bool): Indica si el acceso al campo está denegado (calculado automáticamente).
* `read_disabled` (bool): Inhabilita la lectura del campo (hereda de la tabla si está vacío).
* `filter_disabled` (bool): Inhabilita el filtrado del campo (hereda de la tabla si está vacío).
* `order_disabled` (bool): Inhabilita el ordenamiento del campo (hereda de la tabla si está vacío).
* `is_extra` (bool): Indica si el campo se obtiene de una subconsulta (heredado de la tabla).

**Ejemplo de definición de campos:**

```php
[
    'id' => [],
    'name' => [],
    'email' => [],
    'age' => [],
    'city' => [],
    'status' => [],
    'post_id' => ['name' => 'id', 'table' => 'posts'],
    'post_title' => ['name' => 'title', 'table' => 'posts'],
    'post_content' => ['name' => 'content', 'table' => 'posts'],
    'post_comment_count' => ['name' => 'count', 'table' => 'posts_details']
]
```

#### Creación del esquema

El esquema se crea instanciando la clase Schema y pasando los arrays de configuración de tablas y campos como argumentos:

```php
$schema = new Schema([
    'users' => [],
    'posts' => [
        'dependency' => 'id',
        'join' => ['on' => 'users.id = posts.user_id']
    ],
    'posts_details' => [
        'dependency' => 'post_id',
        'is_extra' => true
    ]
], [
    'id' => [],
    'name' => [],
    'email' => [],
    'age' => [],
    'city' => [],
    'status' => [],
    'post_id' => ['name' => 'id', 'table' => 'posts'],
    'post_title' => ['name' => 'title', 'table' => 'posts'],
    'post_content' => ['name' => 'content', 'table' => 'posts'],
    'post_comment_count' => ['name' => 'count', 'table' => 'posts_details']
]);
```

Una vez creado el esquema, se puede utilizar para construir consultas con DQB.

### Conexión a la base de datos

DQB requiere una conexión a la base de datos para ejecutar consultas. La conexión se establece utilizando la clase `DQB` y puede ser un objeto `PDO` existente o un array con los parámetros de conexión.

#### Conexión con un objeto PDO existente

Si ya tienes una instancia de `PDO` configurada, puedes pasarla al método `setConnection` de la clase `DQB`.

```php
<?php

use DancasDev\DQB\DQB;
use DancasDev\DQB\Schema;
use PDO;

// ... (configuración del esquema)

// Conexión a la base de datos
$connection = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'password');

// Instancia de DQB
$dqb = new DQB($schema);
$dqb->setConnection($connection);
```

#### Conexión con un array de parámetros

También puedes pasar un array con los parámetros de conexión al método `setConnection`. DQB creará una nueva instancia de `PDO` utilizando estos parámetros.

*Parámetros de conexión requeridos:*

* `host` (string): Nombre del host de la base de datos.
* `username` (string): Nombre de usuario para la conexión.
* `password` (string): Contraseña para la conexión.
* `database` (string): Nombre de la base de datos.

*Ejemplo de conexión con un array de parámetros:*

```php
<?php

use DancasDev\DQB\DQB;
use DancasDev\DQB\Schema;

// ... (configuración del esquema)

// Instancia de DQB
$dqb = new DQB($schema);
$dqb->setConnection([
    'host' => 'localhost',
    'username' => 'user',
    'password' => 'password',
    'database' => 'mydb'
]);
```

*Manejo de excepciones*

Si ocurre un error al establecer la conexión utilizando un array de parámetros, el método `setConnection` lanzará una excepción `DQBException`. Es importante capturar esta excepción y manejarla adecuadamente.

```php
<?php

use DancasDev\DQB\DQB;
use DancasDev\DQB\Schema;
use DancasDev\DQB\Exceptions\DQBException;

// ... (configuración del esquema)

$connectionParams = [
    'host' => 'localhost',
    'username' => 'user',
    'password' => 'wrong_password',
    'database' => 'mydb'
];

$dqb = new DQB($schema);

try {
    $dqb->setConnection($connectionParams);
} catch (DQBException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
```

### Preparación y ejecución de consultas
Una vez que el esquema está definido y la conexión a la base de datos está establecida, se pueden preparar y ejecutar consultas utilizando la clase `DQB`.

#### Preparación de la consulta

La preparación de la consulta se realiza utilizando el método `prepare`. Este método acepta los siguientes argumentos:

* `fields` (string): (Opcional) Campos a seleccionar, por defecto `*`.
* `filters` (array): (Opcional) Filtros de la consulta.
* `order` (string): (Opcional) Orden de la consulta.
* `page` (int): (Opcional) Número de página (para paginación).
* `itemsPerPage` (int): (Opcional) Número de elementos por página (para paginación).

**Ejemplo de preparación de consulta:**

```php
$dqb->prepare(
    fields: 'id, name, email',
    filters: ['age', 25, '>='],
    order: 'name:ASC',
    page: 2,
    itemsPerPage: 10
);
```

#### Ejecución de la consulta

La ejecución de la consulta se realiza utilizando los métodos find, count, y countAll.

* `find()`: Devuelve un array con los resultados de la consulta.
* `count()`: Devuelve el número de registros que coinciden con los filtros de la consulta.
* `countAll()`: Devuelve el número total de registros en la tabla principal.

**Ejemplo de ejecución de consulta:**

```php
$count = $dqb->count();
$total = $dqb->countAll();
$results = $dqb->find();

echo "Count: " . $count . "\n";
echo "Total: " . $total . "\n";
print_r($results);
```

**Ejemplo de obtención de consulta SQL:**

```php
$sqlData = $dqb->getSqlData();
echo "SQL: " . $sqlData['query'] . "\n";
print_r($sqlData['params']);
```
## Características Avanzadas

### Función `$dqb->prepare(...)` (Procesadores)

La función `$dqb->prepare(...)` es el núcleo de DQB, encargada de procesar y validar las solicitudes de consulta. Utiliza procesadores especializados para manejar campos, filtros, orden y paginación, convirtiendo las solicitudes en consultas SQL seguras y eficientes.

#### Estructura de Campos

El parámetro `fields` de `$dqb->prepare(...)` define los campos que se seleccionarán en la consulta. DQB admite tres modos de selección de campos:

* **Todos los campos (`"*"`):**
    * Si `fields` es `"*"` , se seleccionan todos los campos definidos en el esquema, respetando los niveles de acceso y los campos habilitados para lectura.
    * Ejemplo:
        ```php
        $dqb->prepare(fields: '*');
        ```
        El resultado SQL sería:
        ```sql
        SELECT user.id, user.name, user.email, user.age, user.city, user.status, posts.id AS post_id, posts.title AS post_title, posts.content AS post_content ...
        ```
* **Campos específicos (Cadena separada por comas):**
    * Define una lista de campos separados por comas.
    * Ejemplo:
        ```php
        $dqb->prepare(fields: 'id, name, email, post_id, post_title');
        ```
        El resultado SQL sería:
        ```sql
        SELECT user.id, user.name, user.email, posts.id AS post_id, posts.title AS post_title ...
        ```
* **Acortadores (Comodín `"*"`):**
    * Permite seleccionar campos mediante prefijos, sufijos o patrones intermedios.
    * Ejemplo:
        * `"prefix_*"`: Selecciona todos los campos que comienzan con `"prefix_"`, a continuación un ejemplo de uso:
            ```php
            $dqb->prepare(fields: 'name, post_*');
            ```
            El resultado SQL sería:
            ```sql
            SELECT user.name, posts.id AS post_id, posts.title AS post_title, posts.content AS post_content  ...
            ```
        * `"*_suffix"` : Selecciona todos los campos que terminan con `"_suffix"`.
            ```php
            $dqb->prepare(fields: 'name, *_id');
            ```
            El resultado SQL sería:
            ```sql
            SELECT user.name, user.id, posts.id AS post_id ...
            ```
        * `"prefix_*_suffix"` : Selecciona todos los campos que comienzan con `"prefix_"` y terminan con `"_suffix"`.
            ```php
            $dqb->prepare(fields: 'name, pos*_id');
            ```
            El resultado SQL sería:
            ```sql
            SELECT user.name, user.id, posts.id AS post_id ...
            ```
#### Estructura de Filtros

El parámetro `filters` define las condiciones `WHERE` de la consulta. Los filtros se representan como arrays con la siguiente estructura:

* **Filtro simple:**
    * Un filtro simple se representa como un array (`["campo", "valor", "operador_relacional", "operador_lógico", "formato_like"]`) con hasta 5 elementos, donde cada elemento tiene un significado específico.
        * `campo` (string): Nombre del campo.
        * `valor` (string, integer, float, bool, null): Valor a comparar.
        * `operador_relacional` (string): (Opcional) `!=`, `>`, `>=`, `<`, `<=`, `LIKE`, `=`. Por defecto, `=`.
        * `operador_lógico` (string): (Opcional)  `AND`, `OR`. Por defecto, `AND`.
        * `formato_like` (string): (Opcional)  `BEFORE`, `AFTER`, `BOTH`. Por defecto, `BOTH` y solo se toma en cuenta si el operador relacional es `LIKE`.
    * Ejemplo:
        ```php
        $dqb->prepare(filters: ["age", 18, ">="]);
        ```
        El resultado SQL sería:
        ```sql
        ... WHERE user.age >= 18
        ```
* **Filtros múltiples:**
    * Un array que contiene múltiples filtros simples.
    * Ejemplo:
        ```php
        $dqb->prepare(filters: [
            ["age", 18, ">="],
            ["city", "New York"]
        ]);
        ```
         El resultado SQL sería:
        ```sql
        ... WHERE user.age >= 18 AND user.city = "New York"
        ```
* **Agrupación de filtros:**
    * Se utilizan los prefijos `"group"` y `"orGroup"` como claves para crear subcondiciones `AND` y `OR`, respectivamente.
    * Ejemplo:
        ```php
        $dqb->prepare(filters: [
            "group_1" => [
                "group" => [["age", 18, ">="], ["age", 30, "<="]],
                "orGroup" => [["age", 40, ">="], ["age", 50, "<="]]
            ]
            "group_2" => [["city", "New York"], ["city", "London", null, "OR"]],
            ["email", "%gmail.com", "LIKE", null, "AFTER"]
        ]);
        ```
        El resultado SQL sería:
        ```sql
        ... WHERE ((user.age >= 18 AND user.age <= 30) OR (user.age >= 40 OR user.age <= 50)) AND (user.city = "New York" OR user.city = "London") AND user.email LIKE "%gmail.com"
        ```

* **Casos especiales (NULL):**
    * `["campo", null, "!="]` se traduce a `WHERE campo IS NOT NULL`.
    * `["campo", null]` se traduce a `WHERE campo IS NULL`.

#### Estructura de Orden

El parámetro `order` define el orden de los resultados. Sigue el formato `"campo:dirección"`, donde:

* `campo` (string): Nombre del campo.
* `dirección` (string): `asc` (ascendente) o `desc` (descendente). Por defecto, `asc`.
* Se pueden especificar múltiples campos de ordenamiento separados por comas.
* Ejemplo: 
    ```php
    $dqb->prepare(order: "age:desc, name:asc");
    ```
    El resultado SQL sería:
    ```sql
    ... ORDER BY user.age DESC, user.name ASC
    ```

#### Estructura de Paginación

Los parámetros `page` y `itemsPerPage` definen la paginación de los resultados:

* `page` (integer): Número de página.
* `itemsPerPage` (integer): Número de elementos por página.
* Se calcula el `OFFSET` como `(page - 1) * itemsPerPage`.
* Ejemplo:
    ```php
    $dqb->prepare(page: 2, itemsPerPage: 10);
    ```
    El resultado SQL sería:
    ```sql
    ... LIMIT 10 OFFSET 10
    ```

### Niveles de Acceso

DQB implementa un sistema de niveles de acceso para controlar la visibilidad de los campos en las consultas. Esto permite restringir el acceso a datos sensibles o campos específicos según el nivel de acceso del usuario que realiza la consulta.

#### Configuración de Niveles de Acceso

* **Nivel de Acceso del Campo:**
    * Cada campo en el esquema de DQB puede tener un nivel de acceso asociado.
    * Este nivel de acceso se define en la configuración del campo y representa el nivel mínimo requerido para acceder a ese campo.
* **Nivel de Acceso de la Consulta:**
    * Al ejecutar una consulta con DQB, se puede especificar el nivel de acceso de la consulta.
    * Solo los campos con un nivel de acceso igual o inferior al nivel de acceso de la consulta serán incluidos en los resultados.

#### Uso de Niveles de Acceso

1.  **Definir Niveles de Acceso en el Esquema:**
    * En la configuración del esquema, asigna un nivel de acceso a cada campo según tus necesidades.
    * Los campos con un nivel de acceso más alto estarán restringidos a usuarios con niveles de acceso más bajos.

2.  **Establecer el Nivel de Acceso de la Consulta:**
    * Antes de ejecutar una consulta, establece el nivel de acceso deseado utilizando el método `setAccessLevel` de la clase `Schema`.

3.  **Ejemplo de Uso:**

    ```php
    use DancasDev\DQB\Schema;

    $schema = new Schema($tablesConfig, $fieldsConfig);

    // Establecer el nivel de acceso de la consulta
    $schema->setAccessLevel(5);

    // Construir la configuración del esquema
    $schema->buildConfig();

    // Ejecutar la consulta
    $dqb = new DQB($schema);
    $dqb->setConnection($connectionConfig);

    // Preparar y ejecutar la consulta
    $result = $dqb->prepare(fields: '*')->find();

    // Imprimir los resultados
    print_r($result);
    ```

    * En este ejemplo, solo los campos con un nivel de acceso igual o inferior a 5 serán incluidos en los resultados de la consulta.

#### Consideraciones

* Los niveles de acceso proporcionan una capa adicional de seguridad para proteger datos sensibles.
* Es importante definir cuidadosamente los niveles de acceso en el esquema para reflejar las políticas de seguridad de la aplicación.
* Los niveles de acceso se aplican a nivel de tabla y/o campo, lo que permite un control granular sobre la visibilidad de los datos.
* Es importante recordar que la implementación de los niveles de acceso depende de la correcta configuración del esquema.

### Carga de Configuración desde Archivos

DQB permite cargar la configuración del esquema desde archivos JSON, facilitando la gestión y el mantenimiento de esquemas complejos. Esto es especialmente útil en aplicaciones donde la configuración del esquema se almacena externamente o se genera dinámicamente.

#### Formatos de Archivos Soportados

* **JSON (.json):**
    * Formato recomendado para la configuración del esquema.
    * Permite definir las configuraciones de tablas y campos de manera estructurada y legible.

#### Uso de la Carga de Configuración

1.  **Crear el Archivo de Configuración:**
    * Crea un archivo JSON que contenga la configuración del esquema.
    * La estructura del archivo debe incluir las claves `"tables"` y `"fields"`, cada una con un array asociativo que define las configuraciones de tablas y campos, respectivamente.
    * Ejemplo de archivo `schema_config.json`:
    ```json
    {
        "tables": {
            "users": {
                "access_level": 0
            },
            "posts": {
                "join": {
                    "type": "LEFT",
                    "on": "users.id = posts.user_id"
                },
                "dependency": ["user_id"],
                "access_level": 1
            }
        },
        "fields": {
            "user_id": {
                "table": "users",
                "name": "id"
            },
            "user_name": {
                "table": "users",
                "name": "name"
            },
            "post_title": {
                "table": "posts",
                "name": "title"
            }
        }
    }
    ```

2.  **Cargar la Configuración:**
    * Utiliza el método `loadConfig` de la clase `Schema` para cargar la configuración desde el archivo.
    * Especifica la ruta del archivo y el formato (por defecto, `"json"`).

3.  **Construir el Esquema:**
    * Después de cargar la configuración, llama al método `buildConfig` para procesar y validar el esquema.

4.  **Ejemplo de Uso:**

    ```php
    use DancasDev\DQB\Schema;

    $schema = new Schema();

    // Cargar la configuración desde el archivo JSON
    $schema->loadConfig('schema_config.json');

    // Construir el esquema
    $schema->buildConfig();

    // Utilizar el esquema en una consulta
    $dqb = new DQB($schema, $pdo);
    $result = $dqb->prepare(fields: '*')->execute();
    ```

#### Consideraciones

* Asegúrate de que el archivo de configuración exista y sea accesible para la aplicación.
* Valida la estructura del archivo JSON para evitar errores de parseo.
* Utiliza rutas relativas o absolutas para especificar la ubicación del archivo de configuración.
* La carga de configuración desde archivos facilita la gestión de esquemas en entornos de desarrollo y producción.

### Caché

DQB permite el uso de caché para almacenar la configuración del esquema, mejorando el rendimiento al evitar la reconstrucción repetida de la misma. Esto es especialmente útil en aplicaciones con esquemas estables y consultas frecuentes.

#### Configuración del Caché

El caché se configura mediante el método `setCache` de la clase `Schema`. Este método acepta los siguientes parámetros:

* `name` (string): Nombre único para la caché.
* `ttl` (int|null): (Opcional) Tiempo de vida (TTL) de la caché en segundos. Si no se especifica, la caché no expirará.
* `dir` (string|object): (Opcional) Directorio donde se almacenará la caché o un objeto que implemente `CacheAdapterInterface`. Por defecto, se utiliza el directorio `writable` dentro del directorio de la librería.

#### Uso del Caché

1.  **Habilitar el Caché:**
    * Llama al método `setCache` en la instancia de `Schema` con los parámetros adecuados.
    * Puedes utilizar el adaptador de caché por defecto (almacenamiento en archivos) o proporcionar tu propio adaptador que implemente `CacheAdapterInterface`.

2.  **Construir la Configuración con Caché:**
    * Cuando se llama al método `buildConfig` de `Schema`, DQB intentará recuperar la configuración del caché.
    * Si la configuración está en caché y no ha expirado, se utiliza directamente, evitando la reconstrucción.
    * Si la configuración no está en caché o ha expirado, se reconstruye y se almacena en caché para futuras solicitudes.

3.  **Ejemplo de Uso:**
    ```php
    use DancasDev\DQB\Schema;
    use DancasDev\DQB\Adapters\MyCustomCacheAdapter; // ejemplo de adaptador personalizado

    $schema = new Schema($tablesConfig, $fieldsConfig);

    // Utilizando el adaptador de caché por defecto (almacenamiento en archivos)
    $schema->setCache('my_schema_cache', 3600); // Caché con TTL de 1 hora

    // Utilizando un adaptador de caché personalizado
    // $schema->setCache('my_schema_cache', 3600, new MyCustomCacheAdapter());

    $schema->buildConfig(); // La configuración se recupera o almacena en caché
    ```

#### Consideraciones

* El caché es especialmente útil para esquemas grandes y complejos que no cambian con frecuencia.
* Asegúrate de que el directorio de caché tenga permisos de escritura adecuados.
* Al utilizar un adaptador de caché personalizado, asegúrate de que implemente correctamente `CacheAdapterInterface`.
* Si modificas la configuración del esquema, considera invalidar la caché para asegurar que las consultas utilicen la configuración actualizada.

### Manejo de Excepciones

DQB utiliza excepciones personalizadas para indicar errores y situaciones excepcionales durante el procesamiento de consultas. Esto permite un manejo de errores más preciso y facilita la depuración de aplicaciones que utilizan DQB.

#### Excepciones Personalizadas de DQB

DQB define las siguientes excepciones personalizadas:

* **`CacheAdapterException`**: Se lanza cuando hay un error relacionado con el adaptador de caché.
* **`FieldsProcessorException`**: Se lanza cuando hay un error al procesar los campos de la consulta.
* **`FiltersProcessorException`**: Se lanza cuando hay un error al procesar los filtros de la consulta.
* **`OrderProcessorException`**: Se lanza cuando hay un error al procesar el orden de la consulta.
* **`SchemaException`**: Se lanza cuando hay un error general relacionado con el esquema de la base de datos.
* **`SchemaFieldConfigBuildException`**: Se lanza cuando hay un error al construir la configuración de un campo en el esquema.
* **`SchemaTableConfigBuildException`**: Se lanza cuando hay un error al construir la configuración de una tabla en el esquema.

#### Manejo de Excepciones

1.  **Bloques `try...catch`**:
    * Utiliza bloques `try...catch` para capturar las excepciones lanzadas por DQB.
    * Puedes capturar excepciones específicas o la excepción base `Exception` para manejar todos los errores.

2.  **Ejemplo de Manejo de Excepciones**:

    ```php
    use DancasDev\DQB\DQB;
    use DancasDev\DQB\Schema;
    use DancasDev\DQB\Exceptions\FieldsProcessorException;
    use DancasDev\DQB\Exceptions\FiltersProcessorException;
    use DancasDev\DQB\Exceptions\OrderProcessorException;
    use DancasDev\DQB\Exceptions\SchemaException;
    use PDO;

    try {
        $pdo = new PDO('mysql:host=localhost;dbname=mydatabase', 'user', 'password');
        $schema = new Schema($tablesConfig, $fieldsConfig);
        $dqb = new DQB($schema, $pdo);
        $result = $dqb->prepare(fields: '*', filters: ['age', 18, '>'])->execute();
        // Procesar los resultados
    } catch (FieldsProcessorException $e) {
        // Manejar errores de procesamiento de campos
        echo "Error de campos: " . $e->getMessage();
    } catch (FiltersProcessorException $e) {
        // Manejar errores de procesamiento de filtros
        echo "Error de filtros: " . $e->getMessage();
    } catch (OrderProcessorException $e) {
        // Manejar errores de procesamiento de orden
        echo "Error de orden: " . $e->getMessage();
    } catch (SchemaException $e) {
        // Manejar errores relacionados con el esquema
        echo "Error de esquema: " . $e->getMessage();
    } catch (Exception $e) {
        // Manejar otros errores
        echo "Error inesperado: " . $e->getMessage();
    }
    ```

#### Consideraciones

* El manejo adecuado de excepciones es crucial para la robustez y la confiabilidad de las aplicaciones que utilizan DQB.
* Captura las excepciones específicas para proporcionar mensajes de error más informativos y tomar acciones correctivas precisas.
* Considera registrar los errores en un archivo de registro o en un sistema de monitoreo para facilitar la depuración y el seguimiento de problemas.

## Contribución:

Las contribuciones son bienvenidas :).