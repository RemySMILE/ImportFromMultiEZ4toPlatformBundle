<?php

/**
 * Created by PhpStorm.
 * User: relim
 * Date: 19/03/18
 * Time: 17:35
 */

namespace Smile\ImportFromMultiEZ4toPlatformBundle\Services;

use DOMDocument;
use DOMXPath;
use eZ\Publish\Core\FieldType\RichText\Converter;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Psr\Log\LoggerInterface;
use eZ\Publish\Core\FieldType\RichText\Converter\Aggregate;
use eZ\Publish\Core\FieldType\XmlText\Converter\Expanding;
use eZ\Publish\Core\FieldType\RichText\Converter\Ezxml\ToRichTextPreNormalize;
use eZ\Publish\Core\FieldType\XmlText\Converter\EmbedLinking;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\FieldType\RichText\Validator;

class ConvertXmlTextToRichTextService
{
    /**
     * @var \eZ\Publish\Core\Persistence\Database\DatabaseHandler
     */
    private $db;

    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Converter
     */
    private $converter;

    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Validator
     */
    private $validator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(DatabaseHandler $db, LoggerInterface $logger = null)
    {

        $this->db = $db;
        $this->logger = $logger;

        $this->converter = new Aggregate(
            array(
                new ToRichTextPreNormalize(new Expanding(), new EmbedLinking()),
                new Xslt(
                    './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/docbook.xsl',
                    array(
                        array(
                            'path' => './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/core.xsl',
                            'priority' => 99,
                        ),
                    )
                ),
            )
        );

        $this->validator = new Validator(
            array(
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/ezpublish.rng',
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/docbook.iso.sch.xsl',
            )
        );
    }


    function createDocument($xmlString)
    {
        $document = new DOMDocument();

        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        $document->loadXML($xmlString);

        return $document;
    }

    function removeComments(DOMDocument $document)
    {
        $xpath = new DOMXpath($document);
        $nodes = $xpath->query('//comment()');

        for ($i = 0; $i < $nodes->length; ++$i) {
            $nodes->item($i)->parentNode->removeChild($nodes->item($i));
        }
    }

    function convert($xmlString)
    {
        $inputDocument = $this->createDocument($xmlString);

        $this->removeComments($inputDocument);

        $convertedDocument = $this->converter->convert($inputDocument);

        // ADD
        $converted = $convertedDocument->saveXML();
        $converted = str_replace('<para>', '', $converted);
        $converted = str_replace('</para>', '', $converted);
        $convertedDocument = $this->createDocument($converted);
        // END

        // Needed by some disabled output escaping (eg. legacy ezxml paragraph <line/> elements)
        $convertedDocumentNormalized = new DOMDocument();
        $convertedDocumentNormalized->loadXML($convertedDocument->saveXML());

        $errors = $this->validator->validate($convertedDocument);

        $result = $convertedDocumentNormalized->saveXML();

        if (!empty($errors)) {
//            $this->logger->error(
//                "Validation errors when converting xmlstring",
//                ['result' => $result, 'errors' => $errors, 'xmlString' => $xmlString]
//            );
            $this->logger->error(
                "Validation errors when converting xmlstring - result",
                ['result' => $result]
            );
            $this->logger->error(
                "Validation errors when converting xmlstring - errors",
                ['errors' => $errors]
            );
            $this->logger->error(
                "Validation errors when converting xmlstring - xmlString",
                ['xmlString' => $xmlString]
            );
        }

        return $result;
    }
}
