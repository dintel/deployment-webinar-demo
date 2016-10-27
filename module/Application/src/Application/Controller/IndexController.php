<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Aws\S3\S3Client;

class IndexController extends AbstractActionController
{

    public function indexAction()
    {
        $vm = new ViewModel();
        if ($this->params()->fromPost("generate")) {
            $errors = [];
            $validated = false;
            if ($this->params()->fromPost("generate")) {
                $minNodes = $this->params()->fromPost("minNodes");
                $maxNodes = $this->params()->fromPost("maxNodes");
                $instanceType = $this->params()->fromPost("instanceType");
                $osType = $this->params()->fromPost("osType");
                $edition = $this->params()->fromPost("edition");
                $resources = json_decode($this->params()->fromPost("resources"), true);

                if (! $minNodes || ! $maxNodes || ! $instanceType) {
                    $errors[] = "Please enter all fields.";
                }

                if (! is_numeric($minNodes) || ! is_numeric($maxNodes)) {
                    $errors[] = "Please insert numbers into the nodes fields.";
                }

                if ($minNodes > $maxNodes) {
                    $errors[] = "Number of maximum nodes must be greater or equal to minimum nodes.";
                }

                if ($minNodes < 2) {
                    $errors[] = "Number of nodes must be greater or equal to 2.";
                }

                if (! in_array($edition, [
                    'pro',
                    'ent',
                    'entplus'
                ])) {
                    $errors[] = "Unknown Zend Server edition selected.";
                }

                if (! is_array($resources)) {
                    $errors[] = "Failed parsing resources.";
                }

                if (count($errors) == 0) {
                    $validated = true;
                }
            }

            if ($validated) {
                $resources = json_decode($this->params()->fromPost("resources"), true);
                $efsResources = array_filter($resources, function ($resource) {
                    return $resource['type'] === "efs";
                });
                $efs = [];
                $additionalSGs = [];
                foreach ($efsResources as $efsResource) {
                    $efs[$efsResource['Id']] = $efsResource['Mountpoint'];
                    if (array_search($efsResource['Sg'], $additionalSGs) === false) {
                        $additionalSGs[] = $efsResource['Sg'];
                    }
                }
                $vm->minNodes = $this->params()->fromPost("minNodes");
                $vm->maxNodes = $this->params()->fromPost("maxNodes");
                $vm->instanceType = $this->params()->fromPost("instanceType");
                $vm->edition = $this->params()->fromPost("edition");
                $vm->osType = $this->params()->fromPost("osType");
                $vm->zendDbType = $this->params()->fromPost("zendDbType");
                $vm->phpVersion = $this->params()->fromPost("phpVersion");
                $vm->withSsl = (bool) $this->params()->fromPost("withSsl", false);
                $vm->resources = array_filter($resources, function ($resource) {
                    return $resource['type'] !== "efs";
                });
                $vm->efs = count($efs) == 0 ? "" : json_encode($efs);
                $vm->additionalSGs = $additionalSGs;
                $vpc = (bool) $this->params()->fromPost("vpc");
                $vm->setTemplate($vpc ? "application/index/template-vpc.phtml" : "application/index/template.phtml");
                $viewRender = $this->getServiceLocator()->get('ViewRenderer');
                $template = $viewRender->render($vm);
                $filename = hash('sha256', microtime() . mt_rand(10000, 90000)) . ".yaml";
                $s3 = new S3Client([
                    'region' => 'us-east-1',
                    'version' => 'latest'
                ]);
                $response = $s3->putObject([
                    'Bucket' => 'phpcloud-templates',
                    'Body' => $template,
                    'ACL' => 'public',
                    'Key' => $filename
                ]);
                $vm = new ViewModel();
                $vm->setTemplate("application/index/result.phtml");
                $vm->templateURL = "https://s3.amazonaws.com/zend-webinar-demo/$filename";
                return $vm;
            }

            $vm->errors = $errors;
        }
        return $vm;
    }
}
