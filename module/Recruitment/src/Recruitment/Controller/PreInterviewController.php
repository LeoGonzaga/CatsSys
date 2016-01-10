<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Recruitment\Controller;

use Database\Service\EntityManagerService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Recruitment\Entity\Address;
use Recruitment\Entity\PreInterview;
use Recruitment\Entity\Registration;
use Recruitment\Form\CpfFilter;
use Recruitment\Form\CpfForm;
use Recruitment\Form\PreInterviewFilter;
use Recruitment\Form\PreInterviewForm;
use RuntimeException;
use Zend\File\Transfer\Adapter\Http as HttpAdapter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * Description of PreInterviewController
 *
 * @author Márcio Dias <marciojr91@gmail.com>
 */
class PreInterviewController extends AbstractActionController
{

    const PRE_INTERVIEW_DIR = './data/pre-interview/';
    const PERSONAL_FILE_SUFFIX = '_personal.pdf';
    const INCOME_FILE_SUFFIX = '_income.pdf';
    const EXPENDURE_FILE_SUFFIX = '_expendure.pdf';

    use EntityManagerService;

    /**
     * @todo Verificar se a entrevista do candidato já foi feita, se sim, faz o bloqueio da pré-entrevista.
     * 
     * @return ViewModel
     */
    public function indexAction()
    {
        $request = $this->getRequest();
        $form = new CpfForm();

        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setInputFilter(new CpfFilter());
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();

                try {

                    $em = $this->getEntityManager();

                    $registration = $em->getRepository('Recruitment\Entity\Registration')
                        ->findOneByPersonCpf($data['cpf']);

                    if ($registration !== null) {
                        if ($registration->getRegistrationConvocationDate() instanceof DateTime) {

                            $studentContainer = new Container('pre_interview');
                            $studentContainer->offsetSet('regId', $registration->getRegistrationId());

                            return $this->redirect()->toRoute('recruitment/pre-interview',
                                    array(
                                    'action' => 'studentPreInterviewFiles'
                            ));
                        }

                        $message = 'Candidato não convocado';
                    } else {
                        $message = 'Candidato não encontrado.';
                    }
                } catch (Exception $ex) {
                    $message = 'Erro inesperado, não foi possível encontrar uma inscrição associada a este cpf.'
                        . $ex->getMessage();
                }
            } else {
                $message = '';
            }
        } else {
            $message = null;
        }

        return new ViewModel(array(
            'message' => $message,
            'form' => $form,
        ));
    }

    public function studentPreInterviewFilesAction()
    {
        $studentContainer = new Container('pre_interview');

        if (!$studentContainer->offsetExists('regId')) {
            return $this->redirect()->toRoute('recruitment/pre-interview', array('action' => 'index'));
        }

        $request = $this->getRequest();

        $files['personal'] = $files['income'] = $files['expendure'] = false;

        $prefix = self::PRE_INTERVIEW_DIR . $studentContainer->offsetGet('regId');

        $files['personal'] = file_exists($prefix . self::PERSONAL_FILE_SUFFIX);
        $files['income'] = file_exists($prefix . self::INCOME_FILE_SUFFIX);
        $files['expendure'] = file_exists($prefix . self::EXPENDURE_FILE_SUFFIX);

        $message = null;
        if ($request->isPost()) {
            if ($files['personal'] && $files['income'] && $files['expendure']) {
                $this->redirect()->toRoute('recruitment/pre-interview',
                    array(
                    'action' => 'studentPreInterviewForm'
                ));
            } else {
                $message = 'Para prosseguir, por favor, envie todos os arquivos.';
            }
        }

        return new ViewModel(array(
            'message' => $message,
            'files' => $files
        ));
    }

    /**
     * Formulário de pré-entrevista
     * 
     * Se a sessão de pré-entrevista não foi criada redireciona para o início da pré-entrevista (indexAction)
     * Se algum(uns) dos arquivos ainda não foi recebido redireciona para a segunda etapa da pré-entrevista 
     * (studentPreInterviewFilesAction)
     * 
     * @return ViewModel
     */
    public function studentPreInterviewFormAction()
    {
        $studentContainer = new Container('pre_interview');

        if (!$studentContainer->offsetExists('regId')) {
            return $this->redirect()->toRoute('recruitment/pre-interview',
                    array(
                    'action' => 'index',
            ));
        }
        $rid = $studentContainer->offsetGet('regId');

        $prefix = self::PRE_INTERVIEW_DIR . $rid;

        $files['personal'] = file_exists($prefix . self::PERSONAL_FILE_SUFFIX);
        $files['income'] = file_exists($prefix . self::INCOME_FILE_SUFFIX);
        $files['expendure'] = file_exists($prefix . self::EXPENDURE_FILE_SUFFIX);

        if (!$files['personal'] || !$files['income'] || !$files['expendure']) {
            return $this->redirect()->toRoute('recruitment/pre-interview',
                    array(
                    'action' => 'studentPreInterviewFilesAction',
            ));
        }
        $message = null;
        $request = $this->getRequest();
        $form = new PreInterviewForm('Pre-interview');

        try {

            $em = $this->getEntityManager();
            $registration = $em->getReference('Recruitment\Entity\Registration', $rid);

            if ($request->isPost()) {
                $data = $request->getPost();
                $form->setInputFilter(new PreInterviewFilter());
                $form->setData($data);

                if ($form->isValid()) {
                    $data = $form->getData();

                    $preInterview = $registration->getPreInterview();
                    if ($preInterview === null) {
                        $preInterview = new PreInterview();
                    }

                    $preInterview
                        ->setPreInterviewPersonalInfo($rid . self::PERSONAL_FILE_SUFFIX)
                        ->setPreInterviewIncomeProof($rid . self::INCOME_FILE_SUFFIX)
                        ->setPreInterviewExpenseReceipt($rid . self::EXPENDURE_FILE_SUFFIX)
                        ->setPreInterviewElementarySchoolType($data['elementary_school_type'])
                        ->setPreInterviewHighSchoolType($data['high_school_type'])
                        ->setPreInterviewHighSchool($data['high_school'])
                        ->setPreInterviewHSConclusionYear($data['hs_conclusion_year'])
                        ->setPreInterviewPreparationSchool($data['preparation_school'])
                        ->setPreInterviewLanguageCourse($data['language_course'])
                        ->setPreInterviewCurrentStudy($data['current_study'])
                        ->setPreInterviewLiveWithNumber($data['live_with_number'])
                        ->setPreInterviewNumberOfRooms($data['number_of_rooms'])
                        ->setPreInterviewMeansOfTransport($data['means_of_transport'])
                        ->setPreInterviewMonthlyIncome($data['monthly_income'])
                        ->setPreInterviewFatherEducationGrade($data['father_education_grade'])
                        ->setPreInterviewMotherEducationGrade($data['mother_education_grade'])
                        ->setPreInterviewExpectFromUs($data['expect_from_us']);

                    $preInterview->clearPreInterviewLiveWithYou();

                    foreach ($data['live_with_you'] as $lwy) {
                        $preInterview->addPreInterviewLiveWithYou($lwy);
                    }

                    $this->insertOrUpdateAddress($registration, $data);
                    
                    $preInterview->setRegistration($registration);
                    $em->persist($preInterview);
                    $em->flush();

                    $form = null;
                    $message = 'Pré-entrevista concluída com com sucesso.';
                }
            }
        } catch (Exception $ex) {
            $registration = $form = null;
            $message = 'Erro inesperado. Por favor, entre em contato com o administrador do sistema.';
            $message = $ex->getMessage();
        }

        return new ViewModel(array(
            'registration' => $registration,
            'form' => $form,
            'message' => $message,
        ));
    }

    /**
     * Salva o endereço do candidato que está fazendo a pré-entrevista
     * 
     * Se o endereço não existe no banco de dados faz o cadastro e associa ao candidato
     * Se o endereço existe e o candidato ainda não possui tal endereço faz apenas a associação do endereço
     * se o endereço existe e o candidato já possui tal endereço não faz nada.
     * 
     * @param Registration $registration
     * @param array $data
     * @return null
     */
    protected function insertOrUpdateAddress(Registration $registration, $data)
    {
        $em = $this->getEntityManager();

        $address = $em->getRepository('Recruitment\Entity\Address')->findOneBy(array(
            'addressCountry' => 'BRASIL',
            'addressState' => $data['state'],
            'addressCity' => $data['city'],
            'addressNeighborhood' => $data['neighborhood'],
            'addressStreet' => $data['street'],
            'addressNumber' => $data['number'],
            'addressComplement' => $data['complement'],
        ));

        $person = $registration->getPerson();

        if ($address !== null) {
            if (!$person->hasAddress($address)) {
                $address->addPerson($person);
            } else {
                return;
            }
        } else {
            $address = new Address();
            $address->setAddressPostalCode($data['postal_code'])
                ->setAddressCountry('BRASIL')
                ->setAddressState($data['state'])
                ->setAddressCity($data['city'])
                ->setAddressNeighborhood($data['neighborhood'])
                ->setAddressStreet($data['street'])
                ->setAddressNumber($data['number'])
                ->setAddressComplement($data['complement'])
                ->addPerson($person);
        }

        $em->persist($address);
    }

    /**
     * Recebe os arquivos de informações pessoais, despesas e renda.
     * 
     * @return JsonModel
     * @throws RuntimeException
     */
    public function studentFileUploadAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            $studentContainer = new Container('pre_interview');
            if ($studentContainer->offsetExists('regId')) {
                $type = $this->params('file', false);

                try {

                    if (!$type) {
                        throw new RuntimeException('O arquivo enviado não é de um dos tipos especificados.');
                    }

                    $file = $request->getFiles()->$type;

                    if ($file === null) {
                        throw new RuntimeException('Nenhum arquivo enviado.');
                    }

                    $targetDir = self::PRE_INTERVIEW_DIR;

                    $filename = $studentContainer->offsetGet('regId');
                    switch ($type) {
                        case 'income':
                            $filename .= self::INCOME_FILE_SUFFIX;
                            break;
                        case 'expendure':
                            $filename .= self::EXPENDURE_FILE_SUFFIX;
                            break;
                        case 'personal':
                            $filename .= self::PERSONAL_FILE_SUFFIX;
                            break;
                    }

                    $targetFile = $targetDir . $filename;

                    $uploadAdapter = new HttpAdapter();

                    $uploadAdapter->addFilter('File\Rename',
                        array(
                        'target' => $targetFile,
                        'overwrite' => true
                    ));

                    $uploadAdapter->setDestination($targetDir);

                    if (!$uploadAdapter->receive($type)) {
                        throw new RuntimeException(implode('\n', $uploadAdapter->getMessages()));
                    }

                    return new JsonModel(array(
                        'message' => 'Arquivo salvo com sucesso.',
                        'file' => $file,
                        'target' => $targetFile
                    ));
                } catch (Exception $ex) {

                    if ($ex instanceof RuntimeException) {
                        $message = 'Erro: ' . $ex->getMessage();
                    } else {
                        $message = 'Erro inesperado. Entre em contato com o administrador do sistema. '
                            . $ex->getMessage();
                    }

                    return new JsonModel(array(
                        'message' => $message,
                    ));
                }
            }

            return new JsonModel(array(
                'message' => 'Sessão expirada. Por favor, retorne a página de inserção de cpf e tente novamente.',
            ));
        }

        return $this->redirect()->toRoute('recruitment/pre-interview', array(
                'index'
        ));
    }

    public function getUploadedFileAction()
    {
        $this->layout('empty/layout');
        $file = $this->params('file', false);
        $rid = $this->params('rid', false);

        if (!$rid) {
            $studentContainer = new Container('pre_interview');
            if ($studentContainer->offsetExists('regId')) {
                $rid = $studentContainer->offsetGet('regId');
            } else {
                return $this->redirect()->toRoute('authorization/index');
            }
        }

        if ($file) {

            $pdf = self::PRE_INTERVIEW_DIR . $rid;
            switch ($file) {
                case 'income':
                    $pdf .= self::INCOME_FILE_SUFFIX;
                    break;
                case 'expendure':
                    $pdf .= self::EXPENDURE_FILE_SUFFIX;
                    break;
                case 'personal':
                    $pdf .= self::PERSONAL_FILE_SUFFIX;
                    break;
            }

            if (file_exists($pdf) !== false) {
                $response = $this->getResponse();
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/pdf');
                $editalContent = file_get_contents($pdf);
                $response->setStatusCode(200);
                $response->setContent($editalContent);
                return $response;
            }

            $message = 'Arquivo não encontrado.';
        } else {
            $message = 'Nenhum tipo de arquivo foi especificado.';
        }

        return new ViewModel(array(
            'message' => $message,
        ));
    }

}