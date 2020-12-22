<?php

namespace Myddleware\InstallBundle\Controller;

use AppKernel;
use Exception;
use Requirement;
use SymfonyRequirements;
// use Doctrine\ORM\ORMException;
// use Doctrine\DBAL\DBALException;
use Symfony\Component\Yaml\Yaml;
// use Doctrine\DBAL\Driver\PDOException;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Myddleware\InstallBundle\Form\DatabaseSetupType;
use Symfony\Component\Console\Output\BufferedOutput;

use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Myddleware\InstallBundle\Entity\DatabaseParameters;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
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

            $form = $this->createForm(DatabaseSetupType::class, $database);
            $form->handleRequest($request);
            
            //send form data input to parameters.yml
            if ($form->isSubmitted() && $form->isValid()) {
                $database = $form->getData();
                $databaseNormalized['parameters'] = $serializer->normalize($database, null);
                //convert the normalized object into a yml file
                $yaml = Yaml::dump($databaseNormalized, 4);
                //push yml content into parameters.yml
                file_put_contents($kernel->getProjectDir() .'/app/config/parameters.yml', $yaml);
                // return $this->redirectToRoute('MyddlewareInstallBundle:Default:database_setup.html.twig');
          

                 $this->connectionSuccessMessage = "Click on the Test button to check your database credentials";
              
                $em = $this->getDoctrine()->getManager();
                $em->getConnection()->connect();
                $connected = $em->getConnection()->isConnected();
  
                // if($connected === false){
                //     $this->connectionSuccessMessage = 'There was an error while trying to your database, please check your parameters and try again.';

                // } else {
                //     $this->connectionSuccessMessage = 'Connection to the database successful';
                // }
             

                // try {

                    // we execute Doctrine console commands to test the connection to the database
                    $application = new Application($kernel);
                    $application->setAutoExit(false);
        
                    $input = new ArrayInput(array(
                        'command' => 'doctrine:schema:update'
                        // '--filename' => "test",
                        // '--extension' => "txt"
                    ));

                    $output = new BufferedOutput();
                    $application->run($input, $output);
            
                    // return the output, don't use if you used NullOutput()
                    $content = $output->fetch();
            
                    //send the message sent by Doctrine to the user's view
                    $this->connectionSuccessMessage = $content;
        

            //         $message = $this->connectionSuccessMessage;
            //     } catch (DBALException $e) {
            //         $message = sprintf('DBALException [%i]: %s', $e->getCode(), $e->getMessage());
            //     } catch (PDOException $e) {
            //         $message = sprintf('PDOException [%i]: %s', $e->getCode(), $e->getMessage());
            //     } catch (ORMException $e) {
            //         $message = sprintf('ORMException [%i]: %s', $e->getCode(), $e->getMessage());
            //     } catch (Exception $e) {
            //         $message = sprintf('Exception [%i]: %s', $e->getCode(), $e->getMessage());
            //     }
        
            // echo $message;



                
                // return new Response(""), if you used NullOutput()
                // return new Response($content);


                // php bin/console doctrine:schema:update -f -e prod
                // Use the NullOutput class instead of BufferedOutput.
                // $output = new NullOutput();
        
                // $application->run($input, $output);



            }
    
        } catch(\Exception $e){
            error_log($e->getMessage());
        }


        return $this->render('MyddlewareInstallBundle:Default:database_setup.html.twig', 
                                array(
                                'form' => $form->createView(),
                                'connection_success_message' =>  $this->connectionSuccessMessage 
                                 )
                                );
    }
}
