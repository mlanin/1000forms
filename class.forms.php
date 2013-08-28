<?php
//class.forms.php
/**
 * @author Maxim Lanin <max@lanin.me>
 */

class Forms {

    const   ENCTYPE_URLENCODED = 'application/x-www-form-urlencoded',
            ENCTYPE_MULTIPART  = 'multipart/form-data';

    const   METHOD_POST = 'POST',
            METHOD_GET  = 'GET';

    const   ERR_REQUIRED    = '"%s" is required',
            ERR_INTEGER     = 'Input "%s" must be integer',
            ERR_FLOAT       = 'Input "%s" must be float',
            ERR_NUMERIC     = 'Input "%s" must be a number',
            ERR_EMAIL       = 'Input "%s" must be a valid EMail',
            ERR_URL         = 'Input "%s" must be a valid URL',
            ERR_IP          = 'Input "%s" must be a valid IP address',
            ERR_MAXLENGTH   = 'Input "%s" must not be longer then %d characters',
            ERR_MINLENGTH   = 'Input "%s" must not be shorter then %d characters',
            ERR_LENGTH      = 'Input "%s" must be %d characters';

    /**
     * Default options of an element
     * @var array
     */
    protected $defaults = array(
        'errors' => array(),
        'prefix' => '',
        'suffix' => '',
        'label'  => '',
        'description' => '',
        'default' => '',
        'value'   => '',
        'options' => array(),
        'attributes' => array(),
        'disabled' => false,
        'readonly' => false,
    );

    /**
     * Default layout of an element
     * @var string
     */
    protected $defaultLayout = '${prefix}${label}${input}${description}${suffix}';

    /**
     * Name of the form
     * @var string
     */
    protected $name = '';
    /**
     * Options of the form
     * @var array
     */
    protected $options = array(
        'prefix' => '',
        'suffix' => '',
        'attributes' => array(),
        'method' => self::METHOD_POST,
        'action' => '',
        'enctype'  => self::ENCTYPE_URLENCODED,
        'validate' => array(),
    );

    /**
     * Array of elements
     * @var array
     */
    protected $elements = array();
    /**
     * Array of submit buttons
     * @var array
     */
    protected $buttons  = array();

    /**
     * Form state
     * @var array
     */
    protected $formState = array();
    /**
     * Validation state
     * @var boolean
     */
    protected $validationState = false;
    /**
     * Submit button that was clicked
     * @var string
     */
    protected $submittedButton = '';

    /**
     * If form must remove empty values from the formState
     * @var boolean
     */
    protected $removeEmpty = false;

    /**
     * If form was inited
     * @var boolean
     */
    protected $inited = false;

    /**
     * Elements counter
     * @var integer
     */
    protected $count  = 0;

    /**
     * Raw data from the form
     * @var array
     */
    protected $data = array();

    /**
     * Constructor.
     * Can take any amount of arguments, that are pushed to init() function
     */
    public function __construct() {
        $this->name = str_replace('\\', '_', strtolower(get_class($this)));
        call_user_func_array(array($this, 'init'), func_get_args());
        $this->inited = true;
    }

    /**
     * Function, that is called right after constructor.
     * Take all arguments, that were pushed into constructor
     */
    public function init() {

    }

    /**
     * Set name of the form
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * Remove Empty values from formstate
     */
    public function rempveEmpty() {
        $this->removeEmpty = true;
    }

    /**
     * Set options of the form
     * @param array $options
     */
    public function setOptions(array $options) {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
    }

    /**
     * Add element to the form
     * @param string $type
     * @param string $name
     * @param array $options
     * @param array $validators
     * @param array $filters
     */
    public function addElement($type, $name, array $options = array(), array $validators = array(), array $filters = array()) {
        if (is_string($type)) {
            if (!is_callable(array($this, 'create' . $type))) {
                $this->error('No such element "' . $type . '"');
            }
        }

        // If it is an array element, then generate its path and ID
        if ($this->isArrayElement($name)) {
            $id   = $this->getElementId($name);
            $path = $this->getElementPath($id);
        } else {
            $id   = $name;
            $path = array($name);
        }

        // Create element array
        $options = array_merge_replace($this->defaults, $options);
        $element = $options + array(
            'id'    => $id,
            'type'  => $type,
            'name'  => $name,
            'path'  => $path,
            'validate'  => $validators,
            'filter'    => $filters,
        );

        // If template isn't set, use default
        if (!isset($element['layout'])) {
            $element['layout'] = $this->defaultLayout;
        }

        // If element is submit button, add it to buttons array
        if ($type == 'submit') {
            $this->buttons[] = $id;
            $element['ignore'] = true;
        }

        // If element is not ignored, add its default value to the formState
        if (!isset($element['ignore'])) {
            $this->formState = array_merge_replace($this->formState, $this->addPathToTree($path, $this->getDefaultState($element)));
        }

        // Set weight to the element
        $this->setWeight($element);

        // Save element
        $this->elements[$id] = $element;
    }

    /**
     * Check if element is array
     * @param string $name
     * @return boolean
     */
    protected function isArrayElement($name) {
        return (bool)strstr($name, '[');
    }

    /**
     * Makes inner ID from the name
     * eg. if name is foo[bar][baz], path will be foo_bar_baz
     * @param type $name
     * @return type
     */
    protected function getElementID($name) {
        return str_replace(array('[]', '][', ']', '['), array('', '_', '', '_'), $name);
    }

    /**
     * Generates path of the element from an ID
     * eg. if name is foo_bar_baz, path will be array('foo', 'bar', 'baz')
     * @param string $id
     * @return array
     */
    protected function getElementPath($id) {
        return explode('_', $id);
    }

    /**
     * Generates path of the element from an ID
     * eg. if name is foo_bar_baz, path will be array('foo', 'bar', 'baz')
     * @param string $id
     * @return array
     */
    protected function getDefaultState($element) {
        return isset($element['default']) ? $element['default'] : '';
    }

    /**
     * Sets weight of an element
     * @param array $element
     */
    protected function setWeight(&$element) {
        $this->count++;

        if (!isset($element['options']['weight'])) {
            $weight = $this->count;
        }
        $element['weight'] = floatval($weight);
    }

    /**
     * Returns if form is valid
     * WARNING! If you don't call it, formState will stay in default state
     * @return boolean
     */
    public function isValid() {
        switch ($this->options['method']) {
            case self::METHOD_POST:
                $this->data = $_POST;
                $this->validate($this->data);
                break;
            case self::METHOD_GET:
                $this->data = $_GET;
                $this->validate($this->data);
                break;
            default:
                break;
        }

        return $this->validationState;
    }

    /**
     * Makes validation of data
     * @param array $data
     * @return boolean
     */
    public function validate(array $data) {
        $errors = 0;

        $formState = array();
        // Check if it is our form
        if (isset($data['_form_name']) && $this->checkForm($data['_form_name'])) {
            // Run throw all elements
            foreach ($this->elements as $id => $element) {
                // Check if we need to ignore it
                if (isset($element['ignore']) && $element['ignore']) {
                    // If it is our submit, save it
                    if (in_array($id, $this->buttons) && isset($data[$id])) {
                        $this->submittedButton = $element['name'];
                    }
                    continue;
                }

                // Try to find value by element path
                $value = null;
                foreach($element['path'] as $name) {
                    // If value == null, ty to find it in main data array
                    if ($value == null) {
                        if (!isset($data[$name])) {
                            $value = '';
                            break;
                        }
                        $value = $data[$name];
                    } else {
                        // if not, then in value itself
                        if (!isset($value[$name])) {
                            $value = '';
                            break;
                        }
                        $value = $value[$name];
                    }
                }

                if (empty($value) && $this->removeEmpty) {
                    continue;
                }

                // Filter value
                $this->filterValues($value, $element);

                if (!$element['disabled']) {
                    // Validate value
                    if ($this->validateValue($value, $element) === false) {
                        $errors++;
                    }

                    if (!$this->removeEmpty || !empty($value)) {
                        $formState = array_merge_replace($formState, $this->addPathToTree($element['path'], $value));
                    }
                } else {
                    //Если поле отключено, то, если нужны пустые значения, устанавливаем его значение в пустую строку
                    if (!$this->removeEmpty) {
                        $formState = array_merge_replace($formState, $this->addPathToTree($element['path'], null));
                    }
                }

                $this->elements[$id] = $element;
            }

            // Save formstate
            $this->formState = $formState;
        } else {
            $errors++;
        }

        // Start validation of the whole form
        foreach ($this->options['validate'] as $validate) {
            if ($validate['name'] == 'callback') {
                $callback = $validate['callback'];
            } else {
                $callback = array($this, $validate['name'] . 'Validator');
            }

            // Fire validation function
            if (is_callable($callback)) {
                if (!call_user_func_array($callback, array($this->formState))) {
                    $errors++;
                    $this->error(isset($validate['message']) ? $validate['message'] : sprintf(self::ERR_CALLBACK, $element['label']));
                }
            } else {
                $this->error('No such validator ' . $validate['name'] . 'Validator');
            }
        }

        $this->validationState = !(bool)$errors;
        return $this->validationState;
    }

    /**
     * Makes validation of values
     * @param array $element
     * @return int
     */
    protected function validateValue($values, &$element) {
        $error = false;

        if (!is_array($values)) {
            $values = array($values);
        }

        // Iterate through all values and validators
        foreach ($values as $value) {
            foreach ($element['validate'] as $validate) {
                switch ($validate['name']) {
                    case 'req':
                    case 'required':
                        if ($value == '' || $value == '-default-') {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_REQUIRED, $element['label']);

                            $exit = true;
                        }
                        break;
                    case 'int':
                    case 'integer':
                        if (!preg_match('/^([0-9]+)$/', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_INTEGER, $element['label']);
                        }
                        break;
                    case 'float':
                        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_FLOAT, $element['label']);
                        }
                        break;
                    case 'num':
                    case 'numeric':
                        if (!preg_match('/^([0-9.]+)$/', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_NUMERIC, $element['label']);
                        }
                        break;
                    case 'str':
                    case 'string':
                        if (!preg_match('/^([0-9a-zA-Z]+)$/', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_STRING, $element['label']);
                        }
                        break;
                    case 'email':
                        if (!preg_match('/^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/ ', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_EMAIL, $element['label']);
                        }
                        break;
                    case 'url':
                        if (!preg_match('/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_URL, $element['label']);
                        }
                        break;
                    case 'maxlength':
                        if (strlen($value) > $validate['length']) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_MAXLENGTH, $element['label'], $validate['length']);
                        }
                        break;
                    case 'minlenght':
                        if (strlen($value) < $validate['length']) {
                            $error = 1;
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_MAXLENGTH, $element['label'], $validate['length']);
                        }
                        break;
                        break;
                    case 'lenght':
                        if (strlen($value) != $validate['length']) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_MAXLENGTH, $element['label'], $validate['length']);
                        }
                        break;
                    case 'ip':
                        if (!preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_IP, $element['label']);
                        }
                        break;
                    case 'regexp':
                        if (!preg_match($validate['pattern'], $value)) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_IP, $element['label']);
                        }
                        break;
                    case 'callback':
                        if (!call_user_func_array($validate['callback'], array($value))) {
                            $error = isset($validate['message']) ?
                                    $validate['message'] : sprintf(self::ERR_CALLBACK, $element['label']);
                        }
                        break;
                    default:
                        if (is_callable(array($this, $validate['name'] . 'Validator'))) {
                            if (!call_user_func_array(array($this, $validate['name'] . 'Validator'), array($value))) {
                                $error = isset($validate['message']) ?
                                        $validate['message'] : sprintf(self::ERR_CALLBACK, $element['label']);
                            }
                        }
                        break;
                }


                if ($error !== false) {
                    $element['errors'][] = $error;
                    $this->error($error);
                    $error = false;
                }

                if (isset($exit)) {
                    break;
                }
            }
        }
        return !(bool)$error;
    }

    /**
     * Filters values
     * @param mixed $values
     * @param array $element
     */
    protected function filterValues(&$values, $element) {
        if (is_array($values)) {
            foreach ($values as &$value) {
                foreach ($element['filter'] as $filter) {
                    $value = $this->filterValue($value, $filter);
                }
            }
        } else {
            foreach ($element['filter'] as $filter) {
                $values = $this->filterValue($values, $filter);
            }
        }
    }

    /**
     * Filteres single value
     * @param mixed $value
     * @param array $filter
     * @return mixed
     */
    protected function filterValue($value, $filter) {
        switch ($filter['name']) {
            case 'addslashes':
                return addslashes($value);
            case 'html':
                return htmlentities($value, ENT_QUOTES, "UTF-8");
            case 'plaintext':
                return strip_tags($value);
            case 'tolower':
                return strtolower($value);
            case 'toupper':
                return strtoupper($value);
            case 'callback':
                return call_user_func_array($filter['callback'], array($value));
            case 'settype':
                switch ($filter['type']) {
                    case 'int':
                    case 'integer':
                        return intval($value);
                    case 'bool':
                    case 'boolean':
                        return (boolean)$value;
                    case 'float':
                        return floatval($value);
                    case 'str':
                    case 'text':
                    case 'string':
                        return strval($value);
                    default:
                        return strval($value);
                }
            default:
                if (is_callable(array($this, $filter['name'] . 'Filter'))) {
                    return call_user_func_array(array($this, $filter['name'] . 'Filter'), array($value));
                }
                break;
        }

        return $value;
    }

    protected function checkForm($name) {
        return $name == $this->name;
    }

    /**
     * Adds path and its value to formstate array
     *
     * Example:
     * $this->_addToFormState(array('field', 'foo', 'bar'), 'some_value');
     * вернет
     * array(
     *      'field' => array(
     *          'foo' => array(
     *              'bar' => 'some_value'
     *          )
     *      )
     * );
     *
     * @param array $path
     * @param mixed $value
     * @return array
     */
    protected function addPathToTree($path, $value) {
        $key = array_pop($path);
        return ($key !== null) ? $this->addPathToTree($path, array($key => $value)) : $value;
    }


    /**
     * Removes path from an tree array
     * @param array $path
     * @param array $state
     */
    protected function removePathFromTree($path, &$state) {
        $key = array_shift($path);
        if (count($path) != 0) {
            $state[$key] = $this->removePathFromTree($path, $state[$key]);
        } else {
            unset($state[$key]);
        }
    }

    /**
     * Returns array of validated values
     * @return array
     */
    public function getFormState() {
        return $this->formState;
    }

    /**
     * Returns array of validated values
     * @return array
     */
    public function getSubmittedButton() {
        return $this->submittedButton;
    }

    /**
     * Generates string of attributes of an alement
     * @param array $attributes
     * @return string
     */
    protected function returnAttributes(array $attributes) {
        if (!empty($attributes)) {
            $attrs = array();
            foreach ($attributes as $key => $value) {
                $attrs[] = $key . '="' . $value . '"';
            }

            return ' ' . join(' ', $attrs);
        } else {
            return '';
        }
    }

    /**
     * Prepares element for html-generation
     * @param array $element
     */
    protected function preprocessElement(&$element) {
        $class = 'element_' . $element['type'] . ' ' . $this->name . '_element_' . $element['type'];

        if (!empty($element['errors'])) {
            $class .= ' validation_error';
        }

        if (isset($element['attributes']['class'])) {
            $class = $class . ' ' . $element['attributes']['class'];
        }

        $element['attributes']['class'] = $class;
    }

    /**
     * Creates element by its template
     * @param array $element
     * @return string
     */
    protected function processElement($element) {
        $parts = array();
        preg_match_all('/\$\{([^{}]+)\}/uim', $element['layout'], $parts);

        if (!isset($parts[1]) || empty($parts[1])) {
            return $element['layout'] . "\r\n";
        }

        foreach ($parts[1] as $key => $part) {
            $html  = '';

            if ($part == 'input') {
                if ($element['type'] instanceof Closure) {
                    $html .= call_user_func_array($element['type'], array($element));
                } else {
                    $html .= $this->{'create' . $element['type']}($element);
                }
            } else {
                $html .= $this->{'create' . $part}($element);

            }

            $element['layout'] = str_replace($parts[0][$key], $html, $element['layout']);
        }

        return $element['layout'] . "\r\n";
    }

    /**
     * Creates select
     * @param array $element
     * @return string
     */
    protected function createSelect($element) {
        $html = '<select name="' . $element['name'] . '" id="' . $element['id'] . '"' .
                $this->returnAttributes($element['attributes']) . '>';

        foreach ($element['options'] as $value => $title) {
            $selected = '';
            if ($element['value'] != '') {
                if ($value == $element['value']) {
                    $selected = ' selected';
                }
            } elseif ($element['default'] != '') {

                if ($value == $element['default']) {
                    $selected = ' selected';
                }
            }
            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' .
                    $title . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Creates radiobutton
     * @param array $element
     * @return string
     */
    protected function createRadiobutton($element) {
        $i = 0;
        $html = '';

        $attributes = $this->returnAttributes($element['attributes']);
        foreach ($element['options'] as $value => $title) {
            $i++;
            $checked = '';
            if ($element['value'] != '') {
                if ($value == $element['value']) {
                    $checked = ' checked="checked" ';
                }
            } elseif ($element['default'] != '') {
                if ($value == $element['default']) {
                    $checked = ' checked="checked" ';
                }
            }
            $html .= '<input type="radio" name="' . $element['name'] . '" id="' . $element['id'] . '-' . $i .
                    '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $checked . '' .
                    $attributes . '/><label for="' . $element['name'] . '-' . $i . '">' . $title . '</label>';
        }

        return $html;
    }

    /**
     * Creates checkbox
     * @param array $element
     * @return string
     */
    protected function createCheckbox($element) {
        $html = '';

        $checked = '';
        if ($element['value'] != '') {
            if ($element['value']) {
                $checked = ' checked="checked"';
            }
        } elseif ($element['default'] != '') {
            if ($element['default']) {
                $checked = ' checked="checked"';
            }
        }

        $html .= '<input type="checkbox" name="' . $element['name'] . '" id="' . $element['id'] .
                '" value="' . $element['name'] . '" ' . $checked;
        $html .= $this->returnAttributes($element['attributes']) . ' />';

        return $html;
    }

    /**
     * Creates textfield
     * @param array $element
     * @return string
     */
    protected function createTextfield($element) {
        $html = '<input type="text" id="' . $element['name'] . '" name="' . $element['id'] . '" ';

        if ($element['default'] != '') {
            $html .= 'value="' . htmlspecialchars($element['default'], ENT_QUOTES, 'UTF-8') . '"';
        }
        $html .= $this->returnAttributes($element['attributes']) . ' />';

        return $html;
    }

    /**
     * Creates password
     * @param array $element
     * @return string
     */
    protected function createPassword($element) {
        return '<input type="password" id="' . $element['name'] . '" name="' . $element['id'] . '" ' .
                $this->returnAttributes($element['attributes']) . ' />';
    }

    /**
     * Creates textarea
     * @param array $element
     * @return string
     */
    protected function createTextarea($element) {
        $html = '<textarea name="' . $element['name'] . '" id="' . $element['id'] .
                $this->returnAttributes($element['attributes']) . '>';

        if ($element['value'] != '') {
            $html .= htmlspecialchars($element['value'], ENT_QUOTES, 'UTF-8');
        } else {
            $html .= htmlspecialchars($element['default'], ENT_QUOTES, 'UTF-8');
        }

        $html.= '</textarea>';

        return $html;
    }

    /**
     * Creates hidden element
     * @param array $element
     * @return string
     */
    protected function createHidden($element) {
        return '<input type="hidden" id="' . $element['name'] . '" name="' . $element['name'] . '" value="' .
                htmlspecialchars($element['value'], ENT_QUOTES, 'UTF-8') . '"' .
                $this->returnAttributes($element['attributes']) . ' />';
    }

    /**
     * Creates button
     *
     * @param array $element
     * @return string
     */
    protected function createButton($element) {
        return '<input type="button" name="' . $element['name'] . '" id="' .
                $element['id'] . '" value="' . $element['value'] . '"' .
                $this->returnAttributes($element['attributes']) . ' />';
    }

    /**
     * Creates submit
     * @param array $element
     * @return string
     */
    protected function createSubmit($element) {
        return '<input type="submit" name="' . $element['name'] . '" id="' . $element['id'] . '" value="' . $element['value'] . '"' .
                $this->returnAttributes($element['attributes']) . ' />';
    }

    /**
     * Creates label
     * @param array $element
     * @return string
     */
    protected function createLabel($element) {
        return !empty($element['label']) ? '<label for="' . $element['name'] . '">' . $element['label'] . ':</label>' : '';
    }

    /**
     * Creates description
     * @param array $element
     * @return string
     */
    protected function createDescription($element) {
        return !empty($element['description']) ? '<div class="description">' . $element['description'] . '</div>' : '';
    }

    /**
     * Creates Errors
     * @param array $element
     * @return string
     */
    protected function createErrors($element) {
        return !empty($element['errors']) ? '<span class="error"><ul><li>' . join('</li><li>', $element['errors']) . '</li></ul></span>' : '';
    }

    /**
     * Creates prefix
     * @param array $element
     * @return string
     */
    protected function createPrefix($element) {
        return !empty($element['prefix']) ? $element['prefix'] . "\r\n" : '';
    }

    /**
     * Creates suffix
     * @param array $element
     * @return string
     */
    protected function createSuffix($element) {
        return !empty($element['suffix']) ? $element['suffix'] . "\r\n" : '';
    }

    protected function createForm() {
        $form  = $this->createPrefix($this->options);
        $form .= '<form method="' . $this->options['method'] . '" name="' . $this->name .
                '" id="' . $this->name . '" action="' . $this->options['action'] . '" enctype="' .
                $this->options['enctype'] . '"' . $this->returnAttributes($this->options['attributes']) . '>' . "\r\n";

        $array = array(
            'form' => $form,
            '/form' => '</form>' . "\r\n" . $this->createSuffix($this->options),
            'elements' => array(),
        );
        return $array;
    }

    /**
     * Throws an error message
     * @param string $error
     * @param integer $code
     * @throws Exception
     */
    public function error($error, $element) {
        throw new Exception($error, $code);
    }

    /**
     * Helper to sort elements
     * @param array $a
     * @param array $b
     * @return int
     */
    protected function sortElements($a, $b) {
        if ($a['weight'] == $b['weight']) {
            return 0;
        }
        return ($a['weight'] < $b['weight']) ? -1 : 1;
    }

    /**
     * Generate html code
     * @return string
     */
    public function generate() {
        $array = $this->toArray();

        if (!empty($this->template)) {
            $html = $this->processTemplate($array, $this->template);
        } else {
            $html  = $array['form'];
            $html .= join('', $array['elements']);
            $html .= $array['/form'];
        }

        return $html;
    }

    /**
     * Form a template from the form array
     * @param array $_vars
     * @param string $_template
     * @return string
     */
    protected function processTemplate($_vars, $_template) {
        if (!is_file($_template)) {
            $this->error("Can't find template file '" . $_template . "'");
        }
        extract($_vars);

        ob_start();
        include($_template);
        return ob_get_clean();
    }

    /**
     * Generate HTML of elements and makes an array from them
     * @return array
     */
    public function toArray() {
        $class = 'form_' . $this->name;
        if (isset($this->options['attriputes']['class'])) {
            $class = $class . ' ' . $this->options['attriputes']['class'];
        }
        $this->options['attriputes']['class'] = $class;

        $array = $this->createForm();

        if (!empty($this->elements)) {
            $this->addElement('hidden', '_form_name', array('value' => $this->name));

            uasort($this->elements, array($this, 'sortElements'));

            foreach ($this->elements as $element) {
                $this->preprocessElement($element);
                $array['elements'][$element['name']] = $this->processElement($element);
            }

        }

        return $array;
    }

    /**
     * Generate html code
     * @return string
     */
    public function __toString() {
        return $this->generate();
    }
}

/**
 * Merges two arrays with replacing similar keys
 * @param array $array
 * @param array $newValues
 * @return array
 */
function array_merge_replace($array, $newValues) {
    foreach ($newValues as $key => $value) {
        if (is_array($value)) {
            if (!isset($array[$key])) {
                $array[$key] = $value;
                continue;
            } else {
                $array[$key] = array_merge_replace($array[$key], $value);
            }
        } else {
            if (isset($array[$key]) && is_array($array[$key])) {
                $array[$key][0] = $value;
            } else {
                if (isset($array) && !is_array($array)) {
                    $temp = $array;
                    $array = array();
                    $array[0] = $temp;
                }
                $array[$key] = $value;
            }
        }
    }
    return $array;
}