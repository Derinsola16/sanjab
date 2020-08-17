<?php

namespace Sanjab\Widgets;

use stdClass;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Sanjab\Helpers\SearchType;
use Sanjab\Traits\ModelEvents;
use Sanjab\Helpers\TableColumn;
use Sanjab\Helpers\PropertiesHolder;
use Sanjab\Traits\ValidationDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base class for all widgets ( form fields and table cells and view ).
 *
 * @method $this onIndex(boolean $val)                  is this element availble on index.
 * @method $this onView(boolean $val)                   is this element availble on view.
 * @method $this onCreate(boolean $val)                 is this element availble on create form.
 * @method $this onEdit(boolean $val)                   is this element availble on edit form.
 * @method $this onStore(boolean $val)                  should this store in database.
 * @method $this sortable(boolean $val)                 is this widget sortable.
 * @method $this searchable(boolean $val)               is this widget searchable.
 * @method $this customStore(callable $val)             store with custom method -  parameters : ($request, $item).
 * @method $this customPreStore(callable $val)          pre store with custom method -  parameters : ($request, $item).
 * @method $this customPostStore(callable $val)         post store with custom method -  parameters : ($request, $item).
 * @method $this customModifyResponse(callable $val)    custom item response modifyer -  parameters : ($response, $item).
 * @method $this customModifyRequest(callable $val)     custom request modify -  parameters : ($request, $item).
 * @method $this value(mixed $val)                      default value for input.
 * @method $this name(string $val)                      field name.
 * @method $this title(string $val)                     field title.
 * @method $this description(string $val)               field description.
 * @method $this indexTag(string $val)                  field default tag in table columns.
 * @method $this viewGroupTag(string $val)              field default tag in show page.
 * @method $this viewTag(string $val)                   field default tag in show page.
 * @method $this tag(string $val)                       field tag.
 * @method $this groupTag(string $val)                  field group tag.
 * @method $this class(string $val)                     class of input field.
 * @method $this cols(string $val)                      bootstrap based column width.
 * @method $this showIf(string $val)                    javascript condition to show widget.
 */
abstract class Widget extends PropertiesHolder
{
    use ModelEvents, ValidationDetails;

    /**
     * Controller properties as array.
     *
     * @var array
     */
    public $controllerProperties = [];

    /**
     * Search types cache.
     *
     * @var null|SearchType[]
     */
    private $searchTypes = null;

    public function __construct(array $properties = [])
    {
        $this->onCreate(true)->onEdit(true)->onIndex(true)->onStore(true)
            ->onView(true)->col(12)->searchable(true)->sortable(true)
            ->indexTag('simple-view')->viewTag('simple-view')->viewGroupTag('simple-view-group')->translation(false)
            ->groupTag('simple-group')->tag('input')->cols(12);
        parent::__construct($properties);
        $this->init();
    }

    /**
     * create new widget.
     *
     * @param  null  $name
     * @param  null  $title
     *
     * @return static
     */
    final public static function create($name = null, $title = null)
    {
        $out = new static();
        if ($name) {
            $out->name($name);
        }

        $title = $title ?: str_replace('_', ' ', Str::title($name));
        $out->title($title);

        return $out;
    }

    /**
     * Called when widget created.
     *
     * @return void
     */
    abstract public function init();

    /**
     * Called when all widgets has been created.
     *
     * @return void
     */
    public function postInit()
    {
        //
    }

    /**
     * Call post init for search widgets.
     *
     * @return void
     */
    final public function postInitSearchWidgets()
    {
        $this->getSearchTypes();
        if (is_array($this->searchTypes)) {
            foreach ($this->searchTypes as $key => $searchType) {
                $this->searchTypes[$key]->postInitWidgets();
            }
        }
    }

    /**
     * Get table columns.
     *
     * @return TableColumn[]
     */
    final public function getTableColumns()
    {
        return $this->property('onIndex') ? $this->tableColumns() : [];
    }

    /**
     * To override table columns creating by this item.
     *
     * @return TableColumn[]
     */
    protected function tableColumns()
    {
        return [
            TableColumn::create($this->property('name'))
                ->title($this->property('title'))
                ->sortable($this->property('sortable'))
                ->tag($this->property('indexTag')),
        ];
    }

    /**
     * Get search types.
     *
     * @return null|array|SearchType[]
     */
    final public function getSearchTypes()
    {
        if ($this->searchTypes == null && $this->property('searchable')) {
            $this->searchTypes = $this->searchTypes();
        }
        if (is_array($this->searchTypes) && count($this->searchTypes) == 0) {
            return;
        }

        return $this->searchTypes;
    }

    /**
     * Get search types.
     *
     * @return array|SearchType[]
     */
    protected function searchTypes(): array
    {
        return [
            SearchType::create('empty', trans('sanjab::sanjab.is_empty')),
            SearchType::create('not_empty', trans('sanjab::sanjab.is_not_empty')),
            SearchType::create('equal', trans('sanjab::sanjab.equal'))
                        ->addWidget(TextWidget::create('search', trans('sanjab::sanjab.equal'))),
            SearchType::create('not_equal', trans('sanjab::sanjab.not_equal'))
                        ->addWidget(TextWidget::create('search', trans('sanjab::sanjab.not_equal'))),
            SearchType::create('similar', trans('sanjab::sanjab.similar'))
                        ->addWidget(TextWidget::create('search', trans('sanjab::sanjab.similar'))),
            SearchType::create('not_similar', trans('sanjab::sanjab.not_similar'))
                        ->addWidget(TextWidget::create('search', trans('sanjab::sanjab.not_similar'))),
        ];
    }

    /**
     * To do search.
     *
     * @param Builder $query
     * @param string $type
     * @param mixed $search
     * @return void
     */
    final public function doSearch(Builder $query, string $type = null, $search = null)
    {
        $searchType = null;
        if ($type != null) {
            $searchType = array_first(array_filter($this->searchTypes(), function (SearchType $searchType) use ($type) {
                return $searchType->type == $type;
            }));
        }
        if ($this->property('searchable') &&
            (
                ($type == null && is_string($search) && ! empty($search)) || ($type != null && (($searchType && count($searchType->getWidgets()) == 0) || ! empty($search)))
            )
        ) {
            $this->search($query, $type, $search);
        }
    }

    /**
     * To override search query modify.
     *
     * @param Builder $query
     * @param string $type
     * @param mixed $search
     * @return void
     */
    protected function search(Builder $query, string $type = null, $search = null)
    {
        $name = $this->property('name');

        switch ($type) {
            case 'empty':
                $query->whereNull($name)->orWhere($name, '=', '');
                break;
            case 'not_empty':
                $query->whereNotNull($name)->where($name, '!=', '');
                break;
            case 'equal':
                $query->where($name, 'LIKE', $search);
                break;
            case 'not_equal':
                $query->where($name, 'NOT LIKE', $search);
                break;
            case 'not_similar':
                $query->where($name, 'NOT LIKE', '%'.$search.'%');
                break;
            default:
                $query->where($name, 'LIKE', '%'.$search.'%');
                break;
        }
    }

    /**
     * To do sort.
     *
     * @param Builder $query
     * @param string $key
     * @param string $direction "asc" or "desc"
     * @return void
     */
    final public function doOrder(Builder $query, string $key, string $direction = 'asc')
    {
        if ($this->property('sortable')) {
            $this->order($query, $key, $direction);
        }
    }

    /**
     * To overide sort query modify.
     *
     * @param Builder $query
     * @param string $key
     * @param string $direction
     * @return void
     */
    protected function order(Builder $query, string $key, string $direction = 'asc')
    {
        $query->orderBy($this->property('name'), $direction);
    }

    /**
     * Do Store request to model!
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    public function doStore(Request $request, Model $item)
    {
        return $this->storeAction($request, $item, 'customStore', 'store');
    }

    private function storeAction($request, $item, $propertyName, $action)
    {
        $propertyValue = $this->property($propertyName);

        if ($this->property('onStore')) {
            if ($propertyValue) {
                return ($propertyValue)($request, $item);
            }

            return $this->$action($request, $item);
        }
    }

    /**
     * Store request to model.
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    protected function store(Request $request, Model $item)
    {
        $name = $this->property('name');
        $item->$name = $request->input($name);
    }

    /**
     * To store on model before validation. for manage temp values and ... if valiadtion faild store will not called.
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    public function doPreStore(Request $request, Model $item)
    {
        return $this->storeAction($request, $item, 'customPreStore', 'preStore');
    }

    /**
     * To override pre store on model.
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    protected function preStore(Request $request, Model $item)
    {
        //
    }

    /**
     * Do Store request to model after save!
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    public function doPostStore(Request $request, Model $item)
    {
        return $this->storeAction($request, $item, 'customPostStore', 'postStore');
    }

    /**
     * Store request to model after save.
     *
     * @param Request $request
     * @param Model $item
     * @return void
     */
    protected function postStore(Request $request, Model $item)
    {
        //
    }

    /**
     * Do modifying request.
     *
     * @param Request $request
     * @param Model|null $item
     * @return void
     */
    final public function doModifyRequest(Request $request, Model $item = null)
    {
        if (is_callable($this->property('customModifyRequest'))) {
            return ($this->property('customModifyRequest'))($request, $item);
        }
        $this->modifyRequest($request, $item);
    }

    /**
     * Do modifying model response.
     *
     * @param object $respones
     * @param Model $item
     * @return void
     */
    final public function doModifyResponse(stdClass $response, Model $item)
    {
        if (is_callable($this->property('customModifyResponse'))) {
            return ($this->property('customModifyResponse'))($response, $item);
        }
        $this->modifyResponse($response, $item);
    }

    /**
     * To override modifying request.
     *
     * @param Request $request
     * @param null|Model $item
     * @return void
     */
    protected function modifyRequest(Request $request, Model $item = null)
    {
        //
    }

    /**
     * To override modifying model response.
     *
     * @param object $respones
     * @param Model $item
     * @return void
     */
    protected function modifyResponse(stdClass $response, Model $item)
    {
        $response->{ $this->property('name') } = $item->{ $this->property('name') };
    }

    /**
     * Returns validation attributes.
     *
     * @param Request $request
     * @param string $type 'create' or 'edit'.
     * @param Model|null $item
     * @return array
     */
    public function validationAttributes(Request $request, string $type, Model $item = null): array
    {
        return [
            $this->name         => $this->title,
            $this->name.'.*'    => $this->title,
        ];
    }

    /**
     * Returns validation rules.
     *
     * @param Request $request
     * @property string $type 'create' or 'edit'.
     * @property Model|null $item
     * @return array
     */
    public function validationRules(Request $request, string $type, Model $item = null): array
    {
        return [
            $this->name => $this->property('rules.'.$type, []),
        ];
    }

    /**
     * Add custom validation rules.
     *
     * @param string|array  $rules
     * @param string $type  'create' or 'edit'
     * @return $this
     */
    final public function rules($rules, $type = null)
    {
        if (empty($rules)) {
            return $this;
        }

        if ($type != 'create' && $type != 'edit') {
            $this->rules($rules, 'create');

            return $this->rules($rules, 'edit');
        }

        if (! is_array($rules)) {
            $rules = explode('|', $rules);
        }
        $thisRules = $this->property('rules', ['create' => [], 'edit' => []]);
        $thisRules[$type] = array_merge($thisRules[$type], $rules);
        $this->setProperty('rules', $thisRules);

        return $this;
    }

    /**
     * Add validation rules for create only.
     *
     * @param string|array  $rules
     * @return $this
     */
    final public function createRules($rules)
    {
        return $this->rules($rules, 'create');
    }

    /**
     * Add validation rules for edit only.
     *
     * @param string|array  $rules
     * @return $this
     */
    final public function editRules($rules)
    {
        return $this->rules($rules, 'edit');
    }

    /**
     * Returns getters.
     *
     * @return array
     */
    public function getGetters()
    {
        return array_merge(['tableColumns', 'searchTypes'], $this->getters);
    }

    /**
     * Widget is multilingal.
     *
     * @param bool $val
     * @return $this
     */
    public function translation(bool $val = true)
    {
        $this->setProperty('sortable', ! $val);
        $this->setProperty('translation', $val);

        return $this;
    }

    /**
     * Add required validation.
     *
     * @return $this
     */
    public function required()
    {
        $this->rules('required');

        return $this;
    }

    /**
     * Add nullable validation.
     *
     * @return $this
     */
    public function nullable()
    {
        $this->rules('nullable');

        return $this;
    }

    /**
     * Prevent show in create, edit form.
     *
     * @return $this
     */
    public function readOnly()
    {
        $this->onCreate(false)->onEdit(false);

        return $this;
    }

    /**
     * Clone current widget.
     *
     * @return static
     */
    public function copy()
    {
        return clone $this;
    }

    public function noEdit()
    {
        $this->onEdit(false);

        return $this;
    }

    public function ajaxy()
    {
        $this->ajax(true);

        return $this;
    }
}
