<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

class QueryFilter
{
    const QUERY_FILTER_KEY = 'query.filter';
    /**
     * Filters that only require a single argument.
     *
     * Are keyed by the arguments name and contain the columnName and a Closure.
     *
     * @var array[]
     */
    protected $singleArgumentFilters = [];
    /**
     * A map from a composite key consisting of the columnName and type of key
     * to the Closure that will resolve the key.
     *
     * @var \Closure[]
     */
    protected $multiArgumentFilters = [];
    
    /**
     * A map from a composite key consisting of the columnName and type of key
     * to a list of argument names associated with it.
     *
     * @var array[]
     */
    protected $multiArgumentFiltersArgNames = [];
    
    /**
     * Get query filter instance for field.
     *
     * @param FieldValue $value
     *
     * @return self
     */
    public static function getInstance(FieldValue $value): QueryFilter
    {
        $handler = static::QUERY_FILTER_KEY
            . '.' . strtolower($value->getParentName())
            . '.' . strtolower($value->getFieldName());

        // Get an existing instance or register a new one
        return app()->has($handler)
            ? resolve($handler)
            : app()->instance($handler, resolve(static::class));
    }

    /**
     * Build query with filter(s).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public static function build($query, array $args)
    {
        // Remove the query filter argument from the args
        $filterInstance = array_pull($args, static::QUERY_FILTER_KEY);
        
        return $filterInstance
            ? $filterInstance->filter($query, $args)
            : $query;
    }
    
    /**
     * Run query through filter.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @param array $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function filter($builder, array $args = [])
    {
        $multiArgFilterValues = [];
        
        /**
         * @var string $key
         * @var mixed $value
         */
        foreach($args as $key => $value){
            /**
             * @var string $filterKey
             * @var string[] $argNames
             */
            foreach($this->multiArgumentFiltersArgNames as $filterKey => $argNames){
                // Gather the values for the filters that take an array of values
                if(in_array($key, $argNames)){
                    $multiArgFilterValues[$filterKey] []= $value;
                }
            }
            
            // Filters that only take a single argument can be applied directly
            if($filterInfo = array_get($this->singleArgumentFilters, $key)){
                $filterCallback = $filterInfo['filter'];
                $columnName = $filterInfo['columnName'];
                
                $builder = $filterCallback($builder, $columnName, $value);
            }
        }
        
        /**
         * @var string $filterKey
         * @var array $values
         */
        foreach($multiArgFilterValues as $filterKey => $values){
            $columnName = str_before($filterKey, '.');
            
            $builder = $this->multiArgumentFilters[$filterKey]($builder, $columnName, $values);
        }
        
        return $builder;
    }
    
    /**
     * @param string $argumentName
     * @param \Closure $filter
     * @param string $columnName
     * @param string $filterType
     *
     * @return QueryFilter
     */
    public function addMultiArgumentFilter(string $argumentName, \Closure $filter, string $columnName, string $filterType): QueryFilter
    {
        $filterKey = "$columnName.$filterType";
        
        $this->multiArgumentFilters[$filterKey] = $filter;
        $this->multiArgumentFiltersArgNames[$filterKey] []= $argumentName;
        
        return $this;
    }
    
    /**
     * @param string $argumentName
     * @param \Closure $filter
     * @param string $columnName
     *
     * @return QueryFilter
     */
    public function addSingleArgumentFilter(string $argumentName, \Closure $filter, string $columnName): QueryFilter
    {
        $this->singleArgumentFilters[$argumentName] = [
            'filter' => $filter,
            'columnName' => $columnName,
        ];
        
        return $this;
    }
}
