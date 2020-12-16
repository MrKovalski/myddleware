<?php

namespace Myddleware\InstallBundle\Controller;

use phpDocumentor\Reflection\Types\Boolean;
use Requirement;
use SymfonyRequirements;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

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
        $this->mydVersion = 'test';
       
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
}
