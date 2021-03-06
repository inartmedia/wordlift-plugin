<?php
namespace Flow\JSONPath\Filters;

class QueryMatchFilter extends AbstractFilter
{

    /**
     * @param array $collection
     * @throws \Exception
     * @return array
     */
    public function filter($collection)
    {
        $return = [];

        preg_match('/@\.([^(?=|>|<|\s)]+)(\s*(==|=|>|<)\s*(\S.+))?/', $this->value, $matches);

        if (!isset($matches[1])) {
            throw new \Exception("Malformed filter query");
        }

        $key      = $matches[1];
        $operator = isset($matches[3]) ? $matches[3] : null;
        $value2   = isset($matches[4]) ? $matches[4] : null;

        if (strtolower($value2) === "false") {
            $value2 = false;
        }
        if (strtolower($value2) === "true") {
            $value2 = true;
        }
        if (strtolower($value2) === "null") {
            $value2 = null;
        }

        $value2 = preg_replace('/^[\'"]/', '', $value2);
        $value2 = preg_replace('/[\'"]$/', '', $value2);

//	    var_dump( $collection );
        foreach ($collection as $value) {
//            echo("====== $key : " . ( $this->keyExists($value, $key) ? 'exists' : 'does not exist' ) . " ======\n");
//            var_dump( $value );
//            echo("======\n");
            if ($this->keyExists($value, $key)) {
                $value1 = $this->getValue($value, $key);

//                echo( "value1: " . var_export( $value1, true ) . "\n" );

                if ($operator === null && $this->keyExists($value, $key)) {
                    $return[] = $value;
                }

                if (($operator === "=" || $operator === "==") && $value1 == $value2) {
                    $return[] = $value;
                }

                if (($operator === "=" || $operator === "==") && is_array( $value1 ) && in_array( $value2, $value1 ) ) {
                    $return[] = $value;
                }

                if (($operator === "!=" || $operator === "!==") && $value1 != $value2) {
                    $return[] = $value;
                }
                if ($operator == ">" && $value1 > $value2) {
                    $return[] = $value;
                }
                if ($operator == "<" && $value1 < $value2) {
                    $return[] = $value;
                }
            }
        }

        return $return;
    }
}
 