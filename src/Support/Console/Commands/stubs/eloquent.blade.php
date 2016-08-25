namespace DummyNamespace;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;
use {{ $model }};

class DummyClass extends GraphQLType{{ $relay ? ' implements RelayType' : '' }}
{
    /**
     * Attributes of Type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => '{{ $shortName }}',
        'description' => '',
    ];

    @if($relay)
    /**
     * Get customer by id.
     *
     * When the root 'node' query is called, it will use this method
     * to resolve the type by providing the id.
     *
     * @param  string $id
     * @return User
     */
    public function resolveById($id)
    {
        return {{ $shortName }}::findOrFail($id);
    }
    @endif

    /**
     * Available fields of Type.
     *
     * @return array
     */
    public function fields()
    {
        return [
@foreach($fields as $key => $field)
            '{{ $key }}' => [
                'type' => {{ $field['type'] }},
                'description' => '{{ $field['description'] }}',
            ],
@endforeach
        ];
    }
}
