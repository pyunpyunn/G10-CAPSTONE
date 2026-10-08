<?php

namespace App\Support;

/** Translate server validation into browser input hints; server validation remains authoritative. */
class FormInputConstraints
{
    public function fromRules(array $rules): array
    {
        $fields = [];
        foreach ($rules as $field => $fieldRules) {
            $constraints = ['required' => in_array('required', $fieldRules, true)];
            foreach ($fieldRules as $rule) {
                if (! is_string($rule)) continue;
                if ((in_array('string', $fieldRules, true) || in_array('email', $fieldRules, true)) && preg_match('/^(min|max):([0-9]+)$/', $rule, $match)) {
                    $constraints[$match[1] === 'min' ? 'minLength' : 'maxLength'] = (int) $match[2];
                }
                if ($rule === 'before:today') $constraints['max'] = now()->subDay()->toDateString();
            }
            $fields[$field] = $constraints;
        }
        return $fields;
    }
}
