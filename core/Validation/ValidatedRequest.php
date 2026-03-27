<?php

namespace Core\Validation;

use Core\Http\Request;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class ValidatedRequest extends Request
{
    private static ?ValidatorInterface $validator = null;

    public static function createFromRequest(Request $request): static
    {
        // Create new instance and copy all data from the original request
        $instance = new static(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );
        
        $instance->populate($request);
        $instance->validate();
        return $instance;
    }

    private function populate(Request $request): void
    {
        // Handle JSON requests
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $content = $request->getContent();
            $data = json_decode($content, true) ?: [];
        } else {
            // Handle form data (query + request parameters)
            $data = $request->all();
        }
        
        $reflection = new ReflectionClass($this);
        
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            if (isset($data[$propertyName])) {
                $property->setValue($this, $data[$propertyName]);
            }
        }
    }

    private function validate(): void
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