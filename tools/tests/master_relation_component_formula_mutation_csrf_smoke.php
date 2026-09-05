<?php

declare(strict_types=1);

/**
 * Behavior-level, DB/network/bootstrap-free smoke for the legacy component
 * formula mutation boundary in Master_relation.
 */

defined('BASEPATH') || define('BASEPATH', __DIR__);

const MRCF_PAGE = 'production.component.formula.index';
const MRCF_FIELD = 'master_relation_component_formula_mutation_csrf';
const MRCF_RECIPE_FIELD = 'master_relation_product_recipe_mutation_csrf';
const MRCF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const MRCF_WRONG = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MRCF_RECIPE_TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

final class MasterRelationFormulaSmokeResponse extends RuntimeException
{
}

final class MasterRelationFormulaSmokeInput
{
    public array $events = [];
    public array $postReads = [];
    public array $getReads = [];
    public array $headerReads = [];
    public int $rawReads = 0;

    private string $requestMethod;
    private array $postData;
    private array $getData;
    private array $headers;
    private string $rawBody;

    public function __construct(
        string $requestMethod,
        array $postData = [],
        array $getData = [],
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->requestMethod = strtoupper($requestMethod);
        $this->postData = $postData;
        $this->getData = $getData;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public function method($upper = false): string
    {
        $this->events[] = 'method';
        return $upper ? $this->requestMethod : strtolower($this->requestMethod);
    }

    public function post($key = null, $xssClean = false)
    {
        $key = $key === null ? '*' : (string)$key;
        $this->postReads[] = $key;
        $this->events[] = 'post:' . $key;
        return $key === '*' ? $this->postData : ($this->postData[$key] ?? null);
    }

    public function get($key = null, $xssClean = false)
    {
        $key = $key === null ? '*' : (string)$key;
        $this->getReads[] = $key;
        return $key === '*' ? $this->getData : ($this->getData[$key] ?? null);
    }

    public function get_request_header($key, $xssClean = false): string
    {
        $key = (string)$key;
        $this->headerReads[] = $key;
        return (string)($this->headers[$key] ?? '');
    }

    public function __get($name)
    {
        if ((string)$name !== 'raw_input_stream') {
            throw new RuntimeException('Unexpected input property: ' . (string)$name);
        }
        $this->rawReads++;
        return $this->rawBody;
    }
}

final class MasterRelationFormulaSmokeSession
{
    public array $values;
    public array $reads = [];
    public array $writes = [];
    public array $flashes = [];

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function userdata($key)
    {
        $key = (string)$key;
        $this->reads[] = $key;
        return $this->values[$key] ?? null;
    }

    public function set_userdata($key, $value = null): void
    {
        $key = (string)$key;
        $this->writes[] = [$key, $value];
        $this->values[$key] = $value;
    }

    public function set_flashdata($key, $value): void
    {
        $this->flashes[] = [(string)$key, (string)$value];
    }
}

final class MasterRelationFormulaSmokeResult
{
    private array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function row_array(): array
    {
        return $this->rows[0] ?? [];
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

final class MasterRelationFormulaSmokeDb
{
    public array $calls = [];

    public function __call($name, $arguments)
    {
        $name = (string)$name;
        $this->calls[] = [$name, $arguments];
        if ($name === 'get') {
            return new MasterRelationFormulaSmokeResult([]);
        }
        if ($name === 'count_all_results') {
            return 0;
        }
        return $this;
    }
}

final class MasterRelationFormulaSmokeModel
{
    public array $calls = [];

    public function get_by_id($table, $id): array
    {
        $table = (string)$table;
        $id = (int)$id;
        $this->calls[] = ['get_by_id', $table, $id];
        if ($table === 'mst_component_formula') {
            return ['id' => $id, 'component_id' => 12, 'line_type' => 'MATERIAL'];
        }
        if ($table === 'mst_component') {
            return ['id' => $id, 'component_name' => 'Fixture Component'];
        }
        return [];
    }

    public function get_options($table, $valueField, $labelField, $activeOnly = false): array
    {
        $this->calls[] = ['get_options', (string)$table];
        return [];
    }

    public function insert($table, array $payload): int
    {
        $this->calls[] = ['insert', (string)$table, $payload];
        return 1;
    }

    public function update($table, $id, array $payload): bool
    {
        $this->calls[] = ['update', (string)$table, (int)$id, $payload];
        return true;
    }
}

final class MasterRelationFormulaSmokeValidation
{
    public array $calls = [];

    public function set_rules($field, $label, $rules): void
    {
        $this->calls[] = ['set_rules', (string)$field];
    }

    public function run(): bool
    {
        $this->calls[] = ['run'];
        return true;
    }
}

class MY_Controller
{
    public MasterRelationFormulaSmokeInput $input;
    public MasterRelationFormulaSmokeSession $session;
    public MasterRelationFormulaSmokeDb $db;
    public MasterRelationFormulaSmokeModel $Master_model;
    public MasterRelationFormulaSmokeValidation $form_validation;
    public array $permissionCalls = [];
    public bool $permissionAllowed = true;
    public ?string $renderedView = null;
    public array $renderedData = [];

    public function __construct()
    {
    }

    public function require_permission($page, $action): void
    {
        $this->permissionCalls[] = [(string)$page, (string)$action];
        if (!$this->permissionAllowed) {
            throw new MasterRelationFormulaSmokeResponse('Forbidden', 403);
        }
    }

    protected function render(string $view, array $data = [], bool $return = false)
    {
        $this->renderedView = $view;
        $this->renderedData = $data;
        return null;
    }
}

function show_error($message, $statusCode = 500, $heading = ''): void
{
    throw new MasterRelationFormulaSmokeResponse((string)$message, (int)$statusCode);
}

function show_404(): void
{
    throw new MasterRelationFormulaSmokeResponse('Not Found', 404);
}

function redirect($uri = '', $method = 'auto', $code = null): void
{
}

function validation_errors($prefix = '', $suffix = ''): string
{
    return 'invalid fixture';
}

require dirname(__DIR__, 2) . '/application/controllers/Master_relation.php';

$checks = 0;
$failures = [];

function mrcf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function mrcf_method_source(string $source, string $method): string
{
    if (preg_match('/(?:public|private|protected) function\s+' . preg_quote($method, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }
    $start = (int)$match[0][1];
    if (preg_match('/\n    (?:public|private|protected) function\s+/', $source, $next, PREG_OFFSET_CAPTURE, $start + 1) !== 1) {
        return substr($source, $start);
    }
    return substr($source, $start, (int)$next[0][1] - $start);
}

function mrcf_fixture(
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRCF_FIELD => MRCF_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    $reflection = new ReflectionClass(Master_relation::class);
    /** @var Master_relation&MY_Controller $controller */
    $controller = $reflection->newInstanceWithoutConstructor();
    $controller->input = new MasterRelationFormulaSmokeInput($method, $post, $get, $headers, $rawBody);
    $controller->session = new MasterRelationFormulaSmokeSession($sessionValues);
    $controller->db = new MasterRelationFormulaSmokeDb();
    $controller->Master_model = new MasterRelationFormulaSmokeModel();
    $controller->form_validation = new MasterRelationFormulaSmokeValidation();
    $controller->permissionAllowed = $permissionAllowed;
    return [$controller, $reflection];
}

function mrcf_invoke_writer(
    string $writer,
    string $method,
    array $post = [],
    bool $permissionAllowed = true,
    array $sessionValues = [MRCF_FIELD => MRCF_TOKEN],
    array $get = [],
    array $headers = [],
    string $rawBody = ''
): array {
    [$controller] = mrcf_fixture($method, $post, $permissionAllowed, $sessionValues, $get, $headers, $rawBody);
    $response = null;
    try {
        $controller->{$writer}($writer === 'component_formula_store' ? 12 : 91);
    } catch (MasterRelationFormulaSmokeResponse $exception) {
        $response = $exception;
    }
    return [$controller, $response];
}

function mrcf_has_call(array $calls, string $method, string $table): bool
{
    foreach ($calls as $call) {
        $target = $call[1] ?? '';
        if (is_array($target)) {
            $target = $target[0] ?? '';
        }
        if (($call[0] ?? '') === $method && $target === $table) {
            return true;
        }
    }
    return false;
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/application/controllers/Master_relation.php');
$formViewSource = (string)file_get_contents($root . '/application/views/master/relation_form.php');
$listViewSource = (string)file_get_contents($root . '/application/views/master/relation_list.php');
$writers = [
    'component_formula_store' => 'create',
    'component_formula_update' => 'edit',
    'component_formula_delete' => 'delete',
];

mrcf_check(
    strpos($controllerSource, "COMPONENT_FORMULA_MUTATION_CSRF_SESSION_KEY = '" . MRCF_FIELD . "'") !== false
        && strpos($controllerSource, "COMPONENT_FORMULA_MUTATION_CSRF_FORM_FIELD = '" . MRCF_FIELD . "'") !== false,
    'controller declares the exact isolated component-formula session key and form field'
);

$tokenSource = mrcf_method_source($controllerSource, 'componentFormulaMutationCsrf');
$guardSource = mrcf_method_source($controllerSource, 'requireComponentFormulaMutationCsrf');
mrcf_check(
    strpos($tokenSource, 'bin2hex(random_bytes(32))') !== false
        && strpos($tokenSource, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'token helper generates and validates strict lowercase 64-hex tokens'
);
mrcf_check(
    strpos($guardSource, "method(true) !== 'POST'") < strpos($guardSource, '->post(')
        && strpos($guardSource, '->post(') < strpos($guardSource, 'hash_equals(')
        && strpos($guardSource, ', 405,') !== false
        && strpos($guardSource, ', 403,') !== false,
    'guard orders POST-only before scoped form token and constant-time comparison'
);
mrcf_check(
    strpos($guardSource, 'get_request_header') === false
        && strpos($guardSource, '->get(') === false
        && strpos($guardSource, 'raw_input_stream') === false
        && strpos($guardSource, 'json_decode') === false,
    'guard contains no query, header, raw JSON, or raw-body fallback'
);

foreach ($writers as $writer => $action) {
    $source = mrcf_method_source($controllerSource, $writer);
    $permissionAt = strpos($source, "requireRelationPermission('formula', '" . $action . "')");
    $guardAt = strpos($source, 'requireComponentFormulaMutationCsrf()');
    $businessMarkers = [
        'Master_model->get_by_id(',
        'form_validation->',
        "->post('line_type'",
        'Master_model->insert(',
        'Master_model->update(',
        "->delete('mst_component_formula'",
    ];
    $firstBusinessAt = strlen($source);
    foreach ($businessMarkers as $marker) {
        $position = strpos($source, $marker);
        if ($position !== false) {
            $firstBusinessAt = min($firstBusinessAt, $position);
        }
    }
    mrcf_check(
        $source !== '' && $permissionAt !== false && $guardAt !== false
            && $permissionAt < $guardAt && $guardAt < $firstBusinessAt,
        $writer . ' orders canonical RBAC, POST/token guard, then business access'
    );

    [$getController, $getResponse] = mrcf_invoke_writer($writer, 'GET');
    mrcf_check($getResponse instanceof MasterRelationFormulaSmokeResponse && $getResponse->getCode() === 405, $writer . ' rejects allowed GET with HTTP 405');
    mrcf_check($getController->permissionCalls === [[MRCF_PAGE, $action]], $writer . ' GET uses exact canonical RBAC');
    mrcf_check($getController->input->postReads === [] && $getController->session->reads === [], $writer . ' GET reads no token or business input');
    mrcf_check($getController->db->calls === [] && $getController->Master_model->calls === [] && $getController->form_validation->calls === [], $writer . ' GET performs no parent/row/model/DB work');

    [$deniedController, $deniedResponse] = mrcf_invoke_writer(
        $writer,
        'POST',
        [MRCF_FIELD => MRCF_TOKEN, 'line_type' => 'MATERIAL'],
        false
    );
    mrcf_check($deniedResponse instanceof MasterRelationFormulaSmokeResponse && $deniedResponse->getCode() === 403, $writer . ' is blocked by RBAC');
    mrcf_check($deniedController->permissionCalls === [[MRCF_PAGE, $action]], $writer . ' denial uses exact canonical RBAC');
    mrcf_check($deniedController->input->events === [] && $deniedController->session->reads === [], $writer . ' RBAC denial precedes method/token reads');
    mrcf_check($deniedController->db->calls === [] && $deniedController->Master_model->calls === [], $writer . ' RBAC denial performs no model/DB work');

    $rejections = [
        'missing' => [[], [], [], ''],
        'malformed' => [[MRCF_FIELD => 'not-hex'], [], [], ''],
        'wrong' => [[MRCF_FIELD => MRCF_WRONG], [], [], ''],
        'cross-scope token' => [[MRCF_FIELD => MRCF_RECIPE_TOKEN], [], [], ''],
        'query fallback' => [[], [MRCF_FIELD => MRCF_TOKEN], [], ''],
        'header fallback' => [[], [], [MRCF_FIELD => MRCF_TOKEN], ''],
        'raw JSON fallback' => [[], [], [], json_encode([MRCF_FIELD => MRCF_TOKEN])],
        'alternate field fallback' => [[MRCF_RECIPE_FIELD => MRCF_TOKEN], [], [], ''],
    ];
    foreach ($rejections as $label => [$post, $get, $headers, $raw]) {
        $post['line_type'] = 'MATERIAL';
        [$invalidController, $invalidResponse] = mrcf_invoke_writer(
            $writer,
            'POST',
            $post,
            true,
            [MRCF_FIELD => MRCF_TOKEN, MRCF_RECIPE_FIELD => MRCF_RECIPE_TOKEN],
            $get,
            $headers,
            (string)$raw
        );
        mrcf_check($invalidResponse instanceof MasterRelationFormulaSmokeResponse && $invalidResponse->getCode() === 403, $writer . ' rejects ' . $label . ' with HTTP 403');
        mrcf_check($invalidController->input->postReads === [MRCF_FIELD], $writer . ' ' . $label . ' reads only the scoped form field');
        mrcf_check($invalidController->input->getReads === [] && $invalidController->input->headerReads === [] && $invalidController->input->rawReads === 0, $writer . ' ' . $label . ' ignores non-form channels');
        mrcf_check($invalidController->db->calls === [] && $invalidController->Master_model->calls === [] && $invalidController->form_validation->calls === [], $writer . ' ' . $label . ' performs no business/write work');
    }
}

[$guardController, $guardReflection] = mrcf_fixture('POST', [MRCF_FIELD => MRCF_TOKEN]);
$guard = $guardReflection->getMethod('requireComponentFormulaMutationCsrf');
$guard->setAccessible(true);
mrcf_check($guard->invoke($guardController) === true, 'valid scoped form token is accepted');
mrcf_check(
    $guardController->input->postReads === [MRCF_FIELD]
        && $guardController->session->reads === [MRCF_FIELD],
    'valid guard reads exactly the component-formula form and session scope'
);

$validPost = [
    MRCF_FIELD => MRCF_TOKEN,
    'line_type' => 'MATERIAL',
    'material_item_id' => 7,
    'line_no' => 1,
    'qty' => 2.5,
    'uom_id' => 3,
    'notes' => 'fixture',
    'sort_order' => 0,
];
foreach ($writers as $writer => $action) {
    [$validController, $validResponse] = mrcf_invoke_writer($writer, 'POST', $validPost);
    mrcf_check($validResponse === null, $writer . ' valid form request completes');
    mrcf_check($validController->permissionCalls === [[MRCF_PAGE, $action]], $writer . ' valid request preserves canonical RBAC');
    if ($writer === 'component_formula_store') {
        $reachedWriter = mrcf_has_call($validController->Master_model->calls, 'insert', 'mst_component_formula');
    } elseif ($writer === 'component_formula_update') {
        $reachedWriter = mrcf_has_call($validController->Master_model->calls, 'update', 'mst_component_formula');
    } else {
        $reachedWriter = mrcf_has_call($validController->db->calls, 'delete', 'mst_component_formula');
    }
    mrcf_check($reachedWriter, $writer . ' valid token reaches its expected writer path');
}

[$newTokenController, $newTokenReflection] = mrcf_fixture('GET', [], true, []);
$tokenHelper = $newTokenReflection->getMethod('componentFormulaMutationCsrf');
$tokenHelper->setAccessible(true);
$generated = (string)$tokenHelper->invoke($newTokenController);
mrcf_check(preg_match('/\A[0-9a-f]{64}\z/D', $generated) === 1, 'missing token generates strict lowercase 64-hex');
mrcf_check(
    count($newTokenController->session->writes) === 1
        && $newTokenController->session->writes[0][0] === MRCF_FIELD,
    'generated token is stored only under the component-formula key'
);

[$malformedController, $malformedReflection] = mrcf_fixture('GET', [], true, [MRCF_FIELD => 'ABC']);
$malformedHelper = $malformedReflection->getMethod('componentFormulaMutationCsrf');
$malformedHelper->setAccessible(true);
$regenerated = (string)$malformedHelper->invoke($malformedController);
mrcf_check(preg_match('/\A[0-9a-f]{64}\z/D', $regenerated) === 1 && $regenerated !== 'ABC', 'malformed session token is regenerated');

$renders = [
    'component_formula' => ['view', 12],
    'component_formula_create' => ['create', 12],
    'component_formula_edit' => ['edit', 91],
];
foreach ($renders as $renderMethod => [$action, $id]) {
    $renderSource = mrcf_method_source($controllerSource, $renderMethod);
    $permissionAt = strpos($renderSource, "requireRelationPermission('formula', '" . $action . "')");
    $tokenAt = strpos($renderSource, 'componentFormulaMutationCsrf()');
    mrcf_check(
        $permissionAt !== false && $tokenAt !== false && $permissionAt < $tokenAt
            && strpos($renderSource, "'" . MRCF_FIELD . "' =>") !== false
            && strpos($renderSource, 'productRecipeMutationCsrf()') === false,
        $renderMethod . ' supplies only component-formula token after canonical RBAC'
    );

    [$authorized] = mrcf_fixture('GET', [], true, []);
    $authorized->{$renderMethod}($id);
    mrcf_check(
        preg_match('/\A[0-9a-f]{64}\z/D', (string)($authorized->renderedData[MRCF_FIELD] ?? '')) === 1
            && !array_key_exists(MRCF_RECIPE_FIELD, $authorized->renderedData),
        $renderMethod . ' authorized render receives only its scoped token'
    );

    [$denied] = mrcf_fixture('GET', [], false, []);
    $deniedResponse = null;
    try {
        $denied->{$renderMethod}($id);
    } catch (MasterRelationFormulaSmokeResponse $exception) {
        $deniedResponse = $exception;
    }
    mrcf_check($deniedResponse instanceof MasterRelationFormulaSmokeResponse && $deniedResponse->getCode() === 403, $renderMethod . ' denied render is blocked by RBAC');
    mrcf_check(
        $denied->session->reads === [] && $denied->session->writes === []
            && $denied->Master_model->calls === [] && $denied->db->calls === [],
        $renderMethod . ' denied render does not generate token or read business data'
    );
}

mrcf_check(substr_count($formViewSource, 'name="' . MRCF_FIELD . '"') === 1, 'generic relation form has exactly one component-formula hidden token');
$componentBranchAt = strpos($formViewSource, '<?php if ($isComponentFormula): ?>', strpos($formViewSource, '<form method="post" action="<?php echo site_url($form_action); ?>">'));
$componentTokenAt = strpos($formViewSource, 'name="' . MRCF_FIELD . '"');
$componentBranchEnd = strpos($formViewSource, '<?php endif; ?>', (int)$componentBranchAt);
mrcf_check(
    $componentBranchAt !== false && $componentTokenAt !== false && $componentBranchEnd !== false
        && $componentBranchAt < $componentTokenAt && $componentTokenAt < $componentBranchEnd,
    'component-formula token is confined to the component-formula form branch'
);
mrcf_check(substr_count($formViewSource, 'name="' . MRCF_RECIPE_FIELD . '"') === 1, 'product-recipe form token remains intact and isolated');

$componentDeleteForm = '<form method="post" action="<?php echo site_url(\'master/relation/component-formula/delete/\'';
$recipeDeleteForm = '<form method="post" action="<?php echo site_url(\'master/relation/product-recipe/delete/\'';
$extraDeleteForm = '<form method="post" action="<?php echo site_url(\'master/relation/product-extra/delete/\'';
mrcf_check(
    strpos($listViewSource, $componentDeleteForm) !== false
        && strpos($listViewSource, 'name="' . MRCF_FIELD . '"') !== false
        && substr_count($listViewSource, "onsubmit=\"return confirm('Hapus relasi ini?')\"") === 2
        && strpos($listViewSource, "href=\"<?php echo site_url('master/relation/component-formula/delete/") === false,
    'component-formula delete is a confirmed scoped POST form with no GET fallback'
);
mrcf_check(
    strpos($listViewSource, $recipeDeleteForm) !== false
        && strpos($listViewSource, 'name="' . MRCF_RECIPE_FIELD . '"') !== false,
    'Batch 55 product-recipe delete POST form remains intact'
);
mrcf_check(
    strpos($listViewSource, $extraDeleteForm) !== false
        && strpos($listViewSource, 'name="master_relation_product_extra_mutation_csrf"') !== false
        && strpos($listViewSource, "onsubmit=\"return confirm('Hapus mapping ini?')\"") !== false
        && strpos($listViewSource, "href=\"<?php echo site_url('master/relation/product-extra/delete/") === false,
    'product-extra delete is a confirmed scoped POST form with no GET fallback'
);

if ($failures !== []) {
    fwrite(STDERR, "Master relation component formula mutation CSRF smoke FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Checks: %d; failures: %d\n", $checks, count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Master relation component formula mutation CSRF smoke passed (%d checks).\n", $checks));
