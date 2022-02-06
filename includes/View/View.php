<?php
namespace SGW_Import\View;

class View
{
    public static function combo(string $name, array $options, ?string $value = null)
    {
        if ($value === null) {
            $value = $_POST[$name] ?? null;
        }
        $v = '<select name="' . $name . '" id="' . $name . '">' . "\n";
        foreach ($options as $key => $option) {
            $selected = $key == $value ? ' selected' : '';
            $v .= '<option value="' . $key . '"' . $selected . '>' . $option . '</option>' . "\n";
        }
        return $v;
    }

}