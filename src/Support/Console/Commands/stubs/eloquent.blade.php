namespace DummyNamespace;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Relay\Support\Definition\RelayType;
use GraphQL\Type\Definition\ResolveInfo;
use {{ $model }};

class DummyClass extends RelayType
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

    /**
     * Available fields of Type.
     *
     * @return array
     */
    public function relayFields()
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
