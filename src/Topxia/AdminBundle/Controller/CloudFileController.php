<?php

namespace Topxia\AdminBundle\Controller;

use Topxia\Common\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Service\CloudPlatform\CloudAPIFactory;

class CloudFileController extends BaseController
{
    public function indexAction()
    {
        try {
            $api    = CloudAPIFactory::create('leaf');
            $result = $api->get("/me");
        } catch (\RuntimeException $e) {
            return $this->render('TopxiaAdminBundle:CloudFile:api-error.html.twig', array());
        }

        $storageSetting = $this->getSettingService()->get('storage', array());

        if (isset($result['hasStorage']) && $result['hasStorage'] == '1' && $storageSetting['upload_mode'] == "cloud") {
            return $this->redirect($this->generateUrl('admin_cloud_file_manage'));
        }

        return $this->render('TopxiaAdminBundle:CloudFile:error.html.twig', array());
    }

    public function manageAction(Request $request)
    {
        $storageSetting = $this->getSettingService()->get('storage', array());

        if ($storageSetting['upload_mode'] != "cloud") {
            return $this->redirect($this->generateUrl('admin_cloud_file'));
        }

        return $this->render('TopxiaAdminBundle:CloudFile:manage.html.twig', array(
            'tags' => $this->getTagService()->findAllTags(0, PHP_INT_MAX)
        ));
    }

    public function renderAction(Request $request)
    {
        $conditions = $request->query->all();
        $results    = $this->getCloudFileService()->search(
            $conditions,
            ($request->query->get('page', 1) - 1) * 20,
            20
        );

        $paginator = new Paginator(
            $this->get('request'),
            $results['count'],
            20
        );

        return $this->render('TopxiaAdminBundle:CloudFile:tbody.html.twig', array(
            'type'         => empty($conditions['type']) ? 'all' : $conditions['type'],
            'materials'    => $results['data'],
            'createdUsers' => isset($results['createdUsers']) ?$results['createdUsers'] : array(),
            'paginator'    => $paginator
        ));
    }

    public function previewAction(Request $reqeust, $globalId)
    {
        $file = $this->getCloudFileService()->getByGlobalId($globalId);
        return $this->render('TopxiaAdminBundle:CloudFile:preview-modal.html.twig', array(
            'file' => $file
        ));
    }

    public function detailAction(Request $reqeust, $globalId)
    {
        try {
            if (!$globalId) {
                return $this->render('TopxiaAdminBundle:CloudFile:detail-not-found.html.twig', array());
            }

            $cloudFile = $this->getCloudFileService()->getByGlobalId($globalId);
        } catch (\RuntimeException $e) {
            return $this->render('TopxiaAdminBundle:CloudFile:detail-not-found.html.twig', array());
        }

        try {
            if ($cloudFile['type'] == 'video') {
                $thumbnails = $this->getCloudFileService()->getDefaultHumbnails($globalId);
            }
        } catch (\RuntimeException $e) {
            $thumbnails = array();
        }

        return $this->render('TopxiaAdminBundle:CloudFile:detail.html.twig', array(
            'material'   => $cloudFile,
            'thumbnails' => empty($thumbnails) ? "" : $thumbnails,
            'params'     => $reqeust->query->all()
        ));
    }

    public function editAction(Request $request, $globalId, $fields)
    {
        $fields = $request->request->all();

        $result = $this->getCloudFileService()->edit($globalId, $fields);
        return $this->createJsonResponse($result);
    }

    public function playerAction(Request $request, $globalId)
    {
        return $this->forward('MaterialLibBundle:GlobalFilePlayer:player', array(
            'globalId' => $globalId
        ));
    }

    public function reconvertAction(Request $request, $globalId)
    {
        $cloudFile = $this->getCloudFileService()->reconvert($globalId, array(
            'directives' => array()
        ));

        if (isset($cloudFile['createdUserId'])) {
            $createdUser = $this->getUserService()->getUser($cloudFile['createdUserId']);
        }

        return $this->render('TopxiaAdminBundle:CloudFile:table-tr.html.twig', array(
            'cloudFile'   => $cloudFile,
            'createdUser' => isset($createdUser) ? $createdUser : array()
        ));
    }

    public function downloadAction($globalId)
    {
        $download = $this->getCloudFileService()->download($globalId);
        return $this->redirect($download['url']);
    }

    public function deleteAction($globalId)
    {
        $result = $this->getCloudFileService()->delete($globalId);
        return $this->createJsonResponse($result);
    }

    protected function createService($service)
    {
        return $this->getServiceKernel()->createService($service);
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getTagService()
    {
        return $this->createService('Taxonomy.TagService');
    }

    protected function getCloudFileService()
    {
        return $this->createService('CloudFile.CloudFileService');
    }
}
