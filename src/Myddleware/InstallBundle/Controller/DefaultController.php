<?php

namespace Myddleware\InstallBundle\Controller;

use Requirement;
use SymfonyRequirements;
use Symfony\Component\Yaml\Yaml;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Myddleware\InstallBundle\Form\DatabaseSetupType;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Myddleware\InstallBundle\Entity\DatabaseParameters;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class DefaultController extends Controller
{
    private $symfonyRequirements;
    private $iniPath;
    private $phpVersion;
    private $urlBase;
    private $mydVersion;
    private $checkPassed;
    private $systemStatus;

    /**
     * @Route("/")
     */
    public function indexAction()
    {
        require_once '../var/SymfonyRequirements.php';
        $this->symfonyRequirements = new SymfonyRequirements();
        // Myddleware specific requirements
        $this->symfonyRequirements->addRequirement(
            is_writable(__DIR__.'/../app/config/parameters.yml'),
            'config/parameters.yml file must be writable',
            'Change the permissions "<strong>config/parameters.yml</strong>" file so that the web server can write into it.'
        );
        $this->symfonyRequirements->addRecommendation(
            is_writable(__DIR__.'/../app/config/public/parameters_public.yml'),
            'config/public/parameters_public.yml file should be writable',
            'Change the permissions "<strong>config/public/parameters_public.yml</strong>" file so that the web server can write into it.'
        );
        $this->symfonyRequirements->addRecommendation(
            is_writable(__DIR__.'/../app/config/public/parameters_smtp.yml'),
            'config/public/parameters_smtp.yml file should be writable',
            'Change the permissions "<strong>config/public/parameters_smtp.yml</strong>" file so that the web server can write into it.'
        );
        // Check php version
        $this->symfonyRequirements->addRequirement( version_compare(phpversion(), '7.2', '>='), 'Wrong php version', 'Your php version is '.phpversion().' and Myddleware is compatible php version >= 7.2');
        $this->symfonyRequirements->addRequirement( version_compare(phpversion(), '7.4', '<'), 'Wrong php version', 'Your php version is '.phpversion().' and Myddleware is compatible php version < 7.4');

        $this->iniPath = $this->symfonyRequirements->getPhpIniConfigPath();
        $this->phpVersion = phpversion();
        $this->urlBase = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].$_SERVER['BASE'];
       
        $errorMesssages = array();
        foreach($this->symfonyRequirements->getRequirements() as $req){
            if(!$req->isFulfilled()){
                $errorMesssages[] = $req->getHelpText();
                $this->checkPassed = false;
            } 
        }
        $recommendationMesssages = array();
        foreach($this->symfonyRequirements->getRecommendations() as $req){
            if(!$req->isFulfilled()){
                $recommendationMesssages[] = $req->getHelpText();
            } 
        }
        $this->systemStatus = '';
        if($this->checkPassed){
            $this->systemStatus = 'Your system is ready to run Myddleware.';
        }else{
            $this->systemStatus ='Your system is not ready to run Myddleware yet.';
        }

        return $this->render('MyddlewareInstallBundle:Default:index.html.twig',
                            array(
                                'url_base' => $this->urlBase,
                                'myd_version' => $this->mydVersion,
                                'ini_path' => $this->iniPath,
                                'php_version' => $this->phpVersion,
                                'error_messages' => $errorMesssages,
                                'system_status' => $this->systemStatus,
                                'recommendation_messages' => $recommendationMesssages
                            ));
    }

    /**
     * @Route("/database/setup")
     */
    public function setupDatabaseAction(Request $request){

        //this will allow us to get the DatabaseParameters object & turn it into an array to push in config/parameters.yml
        $encoder = new JsonEncoder();
        $normalizer = new GetSetMethodNormalizer();
        $serializer = new Serializer([$normalizer], [$encoder]);
    

        //get all parameters from config/parameters.yml and push them in a new instance of DatabaseParameters()
        $database = new DatabaseParameters();
        $database->setDatabaseDriver($this->getParameter('database_driver'));
        $database->setDatabaseHost($this->getParameter('database_host'));
        $database->setDatabasePort($this->getParameter('database_port'));
        $database->setDatabaseName($this->getParameter('database_name'));
        $database->setDatabaseUser($this->getParameter('database_user'));
        $database->setSecret($this->getParameter('secret'));
        $database->setMyddlewareSupport($this->getParameter('myddleware_support'));
        $database->setParam($this->getParameter('param'));
        $database->setExtensionAllowed($this->getParameter('extension_allowed'));
        $database->setMydVersion($this->getParameter('myd_version'));
        $database->setBlockInstall($this->getParameter('block_install'));

        // force user to change the secret
        if($database->getSecret() === 'ThisTokenIsNotSoSecretChangeIt') {
            $database->setSecret(md5(rand(0,10000).date('YmdHis').'myddleware'));
            

        }  elseif($database->getSecret() === '') {
            $database->setSecret(md5(rand(0,10000).date('YmdHis').'myddleware'));

        
        $databaseTest = $serializer->normalize($database, null);
        // foreach($databaseTest as $databaseFieldName => $databaseFieldValue){
        //     if(strpbrk($databaseFieldName, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')){
        //         $explode = explode($array, $databaseFieldName);
        //         var_dump($array);
        //         var_dump($explode);
        //     }
         
     

            var_dump($databaseTest);
            // $yaml = Yaml::dump($databaseArray);
            // var_dump($yaml);
            // file_put_contents('/path/to/file.yml', $yaml);

            var_dump($this->getParameter('secret'));
        } else {
      
        }



        $form = $this->createForm(DatabaseSetupType::class, $database);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $database = $form->getData();
            // return $this->redirectToRoute('MyddlewareInstallBundle:Default:database_setup.html.twig');
        }




        return $this->render('MyddlewareInstallBundle:Default:database_setup.html.twig', 
                                array(
                                'form' => $form->createView() 
                                 )
                                );
    }
}
