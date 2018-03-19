<?php

namespace Smile\ImportFromMultiEZ4toPlatformBundle\Helper;

use eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentStruct;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Symfony\Component\DependencyInjection\Container;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\Command\ConvertXmlTextToRichTextCommand;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Created by PhpStorm.
 * User: relim
 * Date: 16/03/18
 * Time: 15:50
 */
class InitialImportHelper
{
    /** @var Container */
    protected $container;

    /** @var Repository */
    protected $repository;

    /** @var  InputInterface */
    protected $input;
    /** @var  OutputInterface */
    protected $output;

    /** @var  \eZ\Publish\API\Repository\ContentService */
    protected $contentService;
    /** @var  \eZ\Publish\API\Repository\LocationService */
    protected $locationService;
    /** @var \eZ\Publish\API\Repository\ContentTypeService  */
    protected $contentTypeService;
    /** @var \eZ\Publish\API\Repository\SearchService  */
    protected $searchService;

    /** @var  ContentType */
    protected $articleContentType;

    /** @var DatabaseHandler */ // @ezpublish.api.storage_engine.legacy.dbhandler
    protected $dbHandler;// = $container->get("ezpublish.api.storage_engine.legacy.dbhandler");
    /** @var Logger */
    protected $logger;// = $container->get("logger"); // @?logger
    /** @var ConvertXmlTextToRichTextCommand  */
    protected $converter;// = new ConvertXmlTextToRichTextCommand

    /** @var  string Le site d'origine des données */
    public $site;
    /** @var  string L'URL du site d'origine des données */
    public $url;

    /**
     * ChappeeExtension constructor.
     * @param Container $container
     * @param Repository $repository
     */
    public function __construct(Container $container, Repository $repository) {
        $this->container = $container;
        $this->repository = $repository;

        $this->contentService = $repository->getContentService();
        $this->locationService = $repository->getLocationService();
        $this->contentTypeService = $repository->getContentTypeService();
        $this->searchService = $repository->getSearchService();

        $this->articleContentType = $this->contentTypeService->loadContentTypeByIdentifier('article');
        $this->folderContentType = $this->contentTypeService->loadContentTypeByIdentifier('folder');

        /** @var DatabaseHandler $dbHandler */ // @ezpublish.api.storage_engine.legacy.dbhandler
        $this->dbHandler = $container->get("ezpublish.api.storage_engine.legacy.dbhandler");
        /** @var Logger $logger */
        $this->logger = $container->get("logger"); // @?logger

        $this->converter = new ConvertXmlTextToRichTextCommand($this->dbHandler, $this->logger);
    }

    function setInputOutputInterface(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

    }

    public function setSite($site)
    {
        switch ($site) {
            case 'rb'; $site = 'rayon-boissons'; break;
            case 'li'; $site = 'lineaires'; break;
        }

        $url = null;
        switch ($site) {
            case 'rayon-boissons'; $url = 'http://www.rayon-boissons.lxc'; break;
            case 'lineaires'; $url = 'http://www.lineaires.lxc'; break;
        }
        if (!$url) {
            throw new \Exception("Aucune url pour le site '$site'.");
        }

        $this->site = $site;
        $this->url = $url;
    }



    function checkIfContentExist($id, $create_if_not = true)
    {
        $url = $this->url.'/export/item';
        $params = array('id' => $id);

        if (!empty($id['node_id'])) {
            $params = array('node_id' => $id['node_id']);
        } else if (!empty($id['object_id'])) {
            $params = array('id' => $id['object_id']);
        }


        $result = $this->curl_get($url, $params);
        $var = $this->parseResult($result);
        $remote_id = $this->site.'_'.$var['remote_id'];
        $content = $this->getContentByRemoteId($remote_id);
        if (!$content && $create_if_not) {
            $this->createContent($remote_id, $var);
        } elseif ($content && $create_if_not) {
            $this->updateContent($content, $var);
        }
        return !!$content;
    }

    /**
     * @param $remote_id
     * @return null|Content
     */
    function getContentByRemoteId($remote_id)
    {
        try {
            return $this->contentService->loadContentByRemoteId($remote_id);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    function parseResult($result)
    {
        //$this->output->writeln($result);
        $var = null;
        try {
            eval('$var=' . $result . ';');
        //} catch (\Symfony\Component\Debug\Exception\FatalThrowableError $e) {
        } catch (\Exception $e) {
            $this->output->writeln($result);
            $this->output->writeln(print_r($var,1));
            throw $e;
        }
        //$output->writeln(print_r($var,1));
        return $var;
    }

    function createContent($remote_id, $var)
    {
        switch ($var['contentclass_identifier']) {
            // TODO
        }
        $this->createArticle($remote_id, $var);
    }

    function updateContent(Content $content, $var)
    {
        switch ($var['contentclass_identifier']) {
            // TODO
        }
        $this->updateArticle($content, $var);
    }


    function getDependenciesFromRichText($richText)
    {
        //print_r($richText);

        $pattern = '#ezlocation://([0-9]*)#';

        preg_match_all($pattern, $richText, $matches);
        //var_dump($matches);


        $result = array();

        foreach ($matches[1] as $ezlocation) {
            $result[] = $ezlocation;
            echo "\n\n $ezlocation \n";
            $this->checkIfContentExist(['node_id' => $ezlocation], true);
        }

//        /** @var \DOMDocument $document */
//        $document = $this->converter->createDocument($richText);
//var_dump($document);
//var_dump($document->saveXML());
//
//        $result = array();
//
//        // Recherche les liens vers des objets :
//        // <link xlink:href="ezlocation://50033" xlink:show="new">d'un premier spot publicitaire de trois minutes</link>
//        $xpath = new \DOMXPath($document);
//        $nodes = $xpath->query('//link');
//        var_dump($nodes);
//        for ($i = 0; $i < $nodes->length; ++$i) {
//            echo "\n\n";
//            var_dump($nodes->item($i));
//
//        }

        return $result;
    }

    function convert($xmlText)
    {
        // https://doc.ez.no/display/EZP/The+RichText+FieldType
        return $this->converter->convert($xmlText);
    }

    /**
     * Set les fields d'un article
     *
     * @param ContentStruct $contentStruct
     * @param $var
     * @return ContentStruct
     */
    function articleSetField(ContentStruct $contentStruct, $var)
    {

        /*
Title 	title 	ezstring
Short title 	short_title 	ezstring
Author 	author 	ezauthor
Intro 	intro 	ezrichtext
Body 	body 	ezrichtext
Enable comments 	enable_comments 	ezboolean
Image 	image 	ezobjectrelation

    ["title"]=>
    string(36) "Energy-drinks : le flop des dosettes"
    ["short_title"]=>
    string(13) "Energy-drinks"
    ["surtitre"]=>
    string(12) "Consommation"
    ["publish_date"]=>
    string(10) "1274659200"
    ["macaron"]=>
    string(1) "0"
    ["image"]=>
    string(114) "var/rayonboissons/storage/images/boissons-sans-alcool-et-eaux/energy-drinks-10049/78835-2-fre-FR/Energy-drinks.jpg"
    ["aff_vign"]=>
    string(1) "1"
    ["accroche"]=>
    string(182) "Six mois après leur lancement en GMS, les formats concentrés de Red Bull, Dark Dog et Burn peinent à convaincre les distributeurs. En cause : des risques de vols trop importants. "
    ["body"]=>
    string(2865) "<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph> </paragraph><paragraph>Le pari semblait gagné d’avance. Quand, fin 2009, Red Bull, Burn et Dark Dog ont présenté aux distributeurs leur format « shot », un concentré d’energy-drink dans une fiole de quelques centilitres, les principaux acteurs du marché étaient persuadés qu’il s’agissait là de l’innovation de l’année. Force est de constater que, six mois après leur lancement, les résultats sont mitigés. « <emphasize>Mis à part les circuits de proximité, peu d’enseignes d’hypers et de supers ont référencé les shots »</emphasize>, constate-t-on chez Red Bull. Au niveau national, <strong>le groupe Casino est quasiment le seul</strong> à avoir répondu massivement à l’appel. </paragraph><paragraph>Pourquoi une telle réticence de la part des distributeurs ? La principale raison évoquée est le vol. Vendus pour la plupart à l’unité dans des présentoirs en carton ou sur de petites étagères à l’extrémité du rayon, les shots ont vite fait d’atterrir dans les poches des resquilleurs. Un risque de démarque inconnue qui ne séduit pas les distributeurs. </paragraph><paragraph>Les industriels veulent néanmoins rassurer leurs clients. « <emphasize>Là où ils sont référencés, les shots atteignent 15 à 20 % de chiffre d’affaires additionnel sur la catégorie des energy-drinks </emphasize>», assure Pierre Decroix, <strong>vice-président commercial et marketing opérationnel de Coca-Cola Entreprise,</strong> qui commercialise les marques Burn et Monster. Cette dernière signature, qui vient tout juste de lancer ses mini-bouteilles, est elle aussi persuadée de leur potentiel. « <emphasize>La question des vols est le même aux Etats-Unis et en Angleterre. Pourtant, les shots y sont largement distribués</emphasize>, assure Stéphane Munnier, country manager France pour la marque américaine.<emphasize> Il faut simplement leur laisser le temps de s’installer</emphasize> ».</paragraph><paragraph>Le temps, mais aussi la chance. Pour cela, les opérateurs réfléchissent à des solutions qui permettraient de limiter les vols tout en assurant une bonne visibilité. Ils préconisent par exemple une implantation en sortie de caisse. Un emplacement idéal pour les produits d’impulsion tels que les shots. <strong>Dark Dog, quant à lui, mise sur un conditionnement en pack de 10 x 25 ml.</strong> D’autres imaginent encore un système anti-vol, comme il est d’usage pour les spiritueux. Il ne faudrait toutefois pas que de telles protections viennent enfler le prix de ces mini-bouteilles. Lequel est déjà bien élevé.</paragraph></section>
"
    ["author"]=>
    string(11) "Léa Lesurf"
    ["notes_bas_de_page"]=>
    string(220) "<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"/>
"
    ["contenus_lies"]=>
    string(3) "776"
    ["tags"]=>
    string(33) "Burn, Dark Dog, Monster, Red Bull"
        */
        $title = $var['attributes']['title']. ' ' . $this->site .' ' . date('Y-m-d H:i.s');
        $short_title = $var['attributes']['short_title']. ' ' . $this->site . ' ' . date('Y-m-d H:i.s');
        $xmlText = $var['attributes']['body'];
        $converted = $this->convert($xmlText);

        $this->getDependenciesFromRichText($converted);

        $contentStruct->setField('title', $title);
        $contentStruct->setField('short_title', $short_title);
        $contentStruct->setField('intro', $converted);

        return $contentStruct;
    }


    function updateArticle(Content $article, $var)
    {
        // create a content draft from the current published version
        $contentDraft = $this->contentService->createContentDraft( $article->contentInfo );

        // instantiate a content update struct and set the new fields
        $contentStruct = $this->contentService->newContentUpdateStruct();
        $contentStruct->initialLanguageCode = 'eng-GB'; // set language for new version

        $this->articleSetField($contentStruct, $var);

        // update and publish draft

        try {
            $contentDraft = $this->contentService->updateContent( $contentDraft->versionInfo, $contentStruct );
            $content = $this->contentService->publishVersion( $contentDraft->versionInfo );
        } catch (ContentFieldValidationException $e) {
            //var_dump($e);
            foreach ($e->getFieldErrors() as $fieldDefinitionId => $errorLng) {
                foreach ($errorLng as $lng => $error) {
                    echo PHP_EOL.PHP_EOL;
                    var_dump($fieldDefinitionId);
                    var_dump($lng);
                    var_dump($error);
                    echo PHP_EOL.PHP_EOL;
                }
            }
            throw $e;
        }

        //$output->writeln(print_r($content,1));

        return $content;
    }

    function createArticle($remote_id, $var)
    {
        $contentStruct = $this->contentService->newContentCreateStruct($this->articleContentType, 'eng-GB');
        $contentStruct->remoteId = $remote_id;

        $this->articleSetField($contentStruct, $var);

        //$output->writeln(__LINE__);
        $locationCreateStruct = $this->locationService->newLocationCreateStruct( 2 );
        //$output->writeln(print_r($locationCreateStruct,1));

        try {
            $this->output->writeln(__LINE__);
            $draft = $this->contentService->createContent( $contentStruct, array( $locationCreateStruct ) );
            //$output->writeln(print_r($draft,1));
        } catch (ContentFieldValidationException $e) {
            //var_dump($e);
            foreach ($e->getFieldErrors() as $fieldDefinitionId => $errorLng) {
                foreach ($errorLng as $lng => $error) {
                    echo PHP_EOL.PHP_EOL;
                    var_dump($fieldDefinitionId);
                    var_dump($lng);
                    var_dump($error);
                    echo PHP_EOL.PHP_EOL;
                }
            }
            throw $e;
        }
        $this->output->writeln(__LINE__);
        $content = $this->contentService->publishVersion( $draft->versionInfo );
        //$output->writeln(print_r($content,1));
        return $content;
    }

    /**
     * Send a GET requst using cURL
     * @param string $url to request
     * @param array $get values to send
     * @param array $options for cURL
     * @return string
     */
    function curl_get($url, array $get = NULL, array $options = array())
    {
        // guzzle : https://devblog.lexik.fr/symfony2/implementation-dun-client-restful-avec-une-description-guzzle-2756

        $url_get = $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get);
        $this->output->writeln("CALL : ".$url_get);

        $defaults = array(
            CURLOPT_URL => $url_get,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 4
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if( ! $result = curl_exec($ch))
        {
            trigger_error(curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }


}