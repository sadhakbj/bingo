<?php

declare(strict_types=1);

namespace Bingo\Data;

use Bingo\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class DataTransferObject
{
    private static ?ValidatorInterface $validator = null;

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    public static function from(array $data): static
    {
        return new static($data);
    }

    public static function fromRequest($request): static
    {
        if (is_object($request) && method_exists($request, 'all')) {
            $data = $request->all();
        } elseif (is_array($request)) {
            $data = $request;
        } else {
            throw new InvalidArgumentException('Invalid request data provided');
        }

        $instance = new static($data);
        $instance->validate(); // Validate the DTO itself
        return $instance;
    }

    public static function fromModel($model): static
    {
        if (is_object($model) && method_exists($model, 'toArray')) {
            return new static($model->toArray());
        }

        if (is_array($model)) {
            return new static($model);
        }

        throw new InvalidArgumentException('Invalid model data provided');
    }

    private function fill(array $data): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            if (array_key_exists($propertyName, $data)) {
                $value = $data[$propertyName];

                // Handle type casting based on property type
                if ($property->hasType()) {
                    $type = $property->getType();
                    if ($type && !$type->isBuiltin() && is_array($value)) {
                        // Handle nested DTOs
                        $className = $type->getName();
                        if (is_subclass_of($className, DataTransferObject::class)) {
                            $value = new $className($value);
                        }
                    }
                }

                $property->setValue($this, $value);
            }
        }
    }

    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $result     = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            // Check if property is initialized (for readonly properties)
            if (!$property->isInitialized($this)) {
                continue; // Skip uninitialized readonly properties
            }

            $value = $property->getValue($this);

            if ($value instanceof DataTransferObject) {
                $result[$property->getName()] = $value->toArray();
            } elseif (is_array($value)) {
                $result[$property->getName()] = array_map(function ($item) {
                    return $item instanceof DataTransferObject ? $item->toArray() : $item;
                }, $value);
            } else {
                $result[$property->getName()] = $value;
            }
        }

        return $result;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return property_exists($this, $key);
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key) ? $this->{$key} : $default;
    }

    protected function validate(): void
    {
        if (self::$validator === null) {
            self::$validator = Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator();
        }

        $violations = self::$validator->validate($this);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            throw new ValidationException($errors);
        }
    }
}
