<?php

namespace Myddleware\InstallBundle\Controller;

use AppKernel;
use Exception;
use Requirement;
use SymfonyRequirements;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Yaml\Yaml;
use Myddleware\LoginBundle\Entity\User;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Input\ArrayInput;
use Myddleware\InstallBundle\Form\CreateUserType;
use Symfony\Component\Process\PhpExecutableFinder;
use Myddleware\InstallBundle\Form\DatabaseSetupType;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Myddleware\InstallBundle\Entity\DatabaseParameters;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;



class DefaultController extends Controller
{
    private $symfonyRequirements;
    private $iniPath;
    private $phpVersion;
    private $urlBase;
    private $mydVersion;
    private $checkPassed;
    private $systemStatus;
    private $connectionSuccessMessage;
    private $connectionFailedMessage;
    private $fixturesErrorMessage;

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

        try {
            //this will allow us to get the DatabaseParameters object & turn it into an array to push in config/parameters.yml
            $encoder = new YamlEncoder();
            $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
            $serializer = new Serializer([$normalizer], [$encoder]);

            //used to access root dir
            $kernel = new AppKernel('prod', true);
            $env = $kernel->getEnvironment();

            //get ..\bin\php\php.exe file
            $phpBinaryFinder = new PhpExecutableFinder();
            $phpBinaryPath = $phpBinaryFinder->find();

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


            // force user to change the default Symfony secret for security
            if($database->getSecret() === 'ThisTokenIsNotSoSecretChangeIt') {
                $database->setSecret(md5(rand(0,10000).date('YmdHis').'myddleware'));
                $databaseNormalized['parameters'] = $serializer->normalize($database, null);
                //convert the normalized object into a yml file
                $yaml = Yaml::dump($databaseNormalized, 4);
                //push yml content into parameters.yml
                file_put_contents($kernel->getProjectDir() .'/app/config/parameters.yml', $yaml);
            }

            $form = $this->createForm(DatabaseSetupType::class, $database,
            //  array(
            //     'attr' => array(
            //         'onsubmit' => 'return false'
            //     ))
            );
            $form->handleRequest($request);
            
            //send form data input to parameters.yml
            if ($form->isSubmitted() && $form->isValid()) {
                $database = $form->getData();
                $databaseNormalized['parameters'] = $serializer->normalize($database, null);
                //convert the normalized object into a yml file
                $yaml = Yaml::dump($databaseNormalized, 4);
                //push yml content into parameters.yml
                file_put_contents($kernel->getProjectDir() .'/app/config/parameters.yml', $yaml);

                $this->connectionSuccessMessage = "Click on the Test button to check your database credentials";
              
                $em = $this->getDoctrine()->getManager();
                $em->getConnection()->connect();
                $connected = $em->getConnection()->isConnected();
  
                $application = new Application($kernel);
                $application->setAutoExit(false);

                // we execute Doctrine console commands to test the connection to the database
                $input = new ArrayInput(array(
                    'command' => 'doctrine:schema:update',
                    '--force' => true,
                    '--env' => $env
                ));
                $output = new BufferedOutput();
                $application->run($input, $output);
                // return the output, don't use if you used NullOutput()
                $content = $output->fetch();
                //send the message sent by Doctrine to the user's view
                $this->connectionSuccessMessage = $content;

                //load database tables
                $fixturesInput = new ArrayInput(array(
                    'command' => 'doctrine:fixtures:load',
                    '--append' => true,
                    '--env' => $env
                ));

                $fixturesOutput = new BufferedOutput();
                $application->run($fixturesInput, $fixturesOutput);
                $fixturesContent = $fixturesOutput->fetch();
                // $this->fixturesErrorMessage = $fixturesContent;
            }

        // if the user made a mistake on one of the fields, we display the message sent by Doctrine
        } catch (DBALException $e) {
            $message = $e->getMessage();
            $this->connectionFailedMessage = $message;
        }
     


        return $this->render('MyddlewareInstallBundle:Default:database_setup.html.twig', 
                                array(
                                'form' => $form->createView(),
                                'connection_success_message' =>  $this->connectionSuccessMessage,
                                'connection_failed_message' => $this->connectionFailedMessage,
                                // 'fixtures_error_message' => $this->fixturesErrorMessage
                                 )
                                );
    }

    /**
     * @Route("/user/setup")
     */
    public function setupUserAction(Request $request){
        try {   
            $user = new User();
            $em = $this->getDoctrine()->getManager();
            $form = $this->createForm(CreateUserType::class, $user);
            $form->handleRequest($request);
            
            //persist form data to database
            if ($form->isSubmitted() && $form->isValid()) {        
                $user->addRole('ROLE_ADMIN');
                // allows user to login to Myddleware
                $user->setEnabled(true);
                $em->persist($user);
                $em->flush();
                return $this->redirect($this->generateUrl('LoginBundleUser'));
            }

        }catch(Exception $e){
            $message = $e->getMessage();
             // Retrieve flashbag from the controller
             $flashbag = $this->get('session')->getFlashBag();
              // Give confirmation to the user that the form has been sent
              $flashbag->add("error", $message);
        }

        return $this->render('MyddlewareInstallBundle:Default:user_setup.html.twig',
                                                        array(
                                                            'form' => $form->createView(),
                                                        )
                            );
    }


}
