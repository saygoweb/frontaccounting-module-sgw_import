<?php
namespace SGW_Import\Import;

class Column
{
    /** @var string */
    public $name;

    /** @var bool */
    public $hidden;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->hidden = false;
    }

    /** @return Column[] */
    public static function createByArray(array $names, array $hidden)
    {
        $columns = [];
        foreach ($names as $name) {
            $c = new Column($name);
            $c->hidden = in_array($name, $hidden);
            $columns[] = $c;
        }
        return $columns;
    }

}
