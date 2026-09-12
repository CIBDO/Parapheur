<?php

namespace App\Support;

/**
 * Extensions autorisées pour les pièces jointes du parapheur.
 */
final class AllowedDocumentUploads
{
    public const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,txt,png,jpg,jpeg,gif,webp';

    /**
     * @return list<string>
     */
    public static function fileRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:20480',
            'mimes:'.self::MIMES,
        ];
    }
}
