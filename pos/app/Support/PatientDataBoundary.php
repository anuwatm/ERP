<?php

namespace Pos\Support;

final class PatientDataBoundary
{
    public static function contains(array $data, int $depth = 0): bool
    {
        if ($depth > 32) {
            return true;
        }
        foreach ($data as $key => $value) {
            $name = strtolower(str_replace(['_', '-'], '', (string) $key));
            if (str_starts_with($name, 'patient') || in_array($name, ['medicalrecord', 'clinicalnote', 'allergies', 'birthdate'], true)) {
                return true;
            }
            if (is_array($value) && self::contains($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
