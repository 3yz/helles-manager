<?php

namespace Tresyz\HellesManager\Generators;

use Illuminate\Support\Pluralizer;
use Way\Generators\Generators\Generator;

class ViewGenerator extends Generator {

    /**
     * Fetch the compiled template for a view
     *
     * @param  string $template Path to template
     * @param  string $name
     * @return string Compiled template
     */
    protected function getTemplate($template, $name)
    {
        $this->template = $this->file->get($template);

        if ($this->needsScaffolding($template))
        {
            return $this->getScaffoldedTemplate($name);
        }

        // Otherwise, just set the file
        // contents to the file name
        return $name;
    }

    /**
     * Get the scaffolded template for a view
     *
     * @param  string $name
     * @return string Compiled template
     */
    protected function getScaffoldedTemplate($name)
    {
        $model = $this->cache->getModelName();  // post
        $models = Pluralizer::plural($model);   // posts
        $Models = ucwords($models);             // Posts
        $Model = Pluralizer::singular($Models); // Post

        // Create and Edit views require form elements
        if ($name === '_form.blade')
        {
            $formElements = $this->makeFormElements();

            $this->template = str_replace('{{formElements}}', $formElements, $this->template);
        }

        // Replace template vars in view
        foreach(array('model', 'models', 'Models', 'Model') as $var)
        {
            $this->template = str_replace('{{'.$var.'}}', $$var, $this->template);
        }

        // And finally create the table rows
        list($headings, $fields, $editAndDeleteLinks) = $this->makeTableRows($model);
        $this->template = str_replace('{{headings}}', implode(PHP_EOL."              ", $headings), $this->template);
        $this->template = str_replace('{{fields}}', implode(PHP_EOL."              ", $fields) . PHP_EOL . $editAndDeleteLinks, $this->template);

        return $this->template;
    }

    /**
     * Create the table rows
     *
     * @param  string $model
     * @return Array
     */
    protected function makeTableRows($model)
    {
        $models = Pluralizer::plural($model); // posts

        $fields = $this->cache->getFields();

        // First, we build the table headings
        $headings = array_map(function($field) {
            return '<th>' . ucwords($field) . '</th>';
        }, array_keys($fields));
        $headings[] = '<th class="actions">Ações</th>';

        // And then the rows, themselves
        $fields = array_map(function($field) use ($model) {
            return "<td>{{{ \$$model->$field }}}</td>";
        }, array_keys($fields));

        // Now, we'll add the edit and delete buttons.
        $editAndDelete = <<<EOT
              <td>
                {{ HTML::decode(link_to_route('admin.{$models}.edit', '<i class="fa fa-edit"></i>', \${$model}->id,  array('class' => 'btn'))) }}
                {{ HTML::decode(link_to_route('admin.{$models}.destroy','<i class="fa fa-trash-o"></i>', \${$model}->id, array('class' => 'btn btn-danger delete'))) }}
              </td>
EOT;

        return array($headings, $fields, $editAndDelete);
    }

    /**
     * Add Laravel methods, as string,
     * for the fields
     *
     * @return string
     */
    public function makeFormElements()
    {
        $formMethods = array();

        foreach($this->cache->getFields() as $name => $type)
        {
            $formalName = ucwords($name);

            // TODO: add remaining types
            switch($type)
            {
                case 'integer':
                    if (substr($name, -3) == '_id') {
                      $element = "{{ Form::select('$name', array(), NULL, array('class' => 'form-control')) }}";
                    } else {
                      $element = "{{ Form::input('number', '$name', NULL, array('class' => 'form-control')) }}";
                    }
                    break;

                case 'text':
                    $element = "{{ Form::textarea('$name', NULL, array('class' => 'form-control richtext')) }}";
                    break;

                case 'boolean':
                    $element = "{{ Form::checkbox('$name') }}";
                    break;

                default:
                    $element = "{{ Form::text('$name', NULL, array('class' => 'form-control')) }}";
                    break;
            }

            // Now that we have the correct $element,
            // We can build up the HTML fragment
            $error_control = '{{$errors->has(\''.$name.'\') ? \'has-error\' : \'\'}}';
            $frag = <<<EOT
        <div class="form-group $error_control ">
          {{ Form::label('$name', '$formalName', array('class' => 'col-sm-2 control-label')) }}
          <div class="col-sm-4">
            $element
            <small class="help-block"></small> 
          </div>
        </div>
EOT;

            $formMethods[] = $frag;
        }

        return implode(PHP_EOL, $formMethods);
    }

}
