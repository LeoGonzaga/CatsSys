<?php

/*
 * Copyright (C) 2016 Gabriel Pereira <rickardch@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SchoolManagement\Controller;

use Database\Controller\AbstractEntityActionController;
use SchoolManagement\Form\SearchQuestionsForm;
use SchoolManagement\Form\AddExamQuestionForm;
use SchoolManagement\Entity\ExamQuestion;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Exception;

/**
 * Description of SchoolExamController
 *
 * @author Gabriel Pereira <rickardch@gmail.com>
 */
class SchoolExamController extends AbstractEntityActionController
{

    /**
     * Exibe uma tabela com todos os simulados gerados
     * 
     * @return ViewModel
     */
    public function indexAction()
    {
        return new ViewModel(array(
            'message' => null,
        ));
    }

    /**
     * Retorna todas as questões cadastradas para a matéria $data['subject'] do tipo $data['questionType']
     * 
     * @return JsonModel
     */
    public function getQuestionsAction()
    {
        $request = $this->getRequest();
        $result = [];

        if ($request->isPost()) {
            try {
                $em = $this->getEntityManager();
                $form = new SearchQuestionsForm($em);
                $form->setData($request->getPost());

                if ($form->isValid()) {
                    $data = $form->getData();
                    $subject = $em->getReference('SchoolManagement\Entity\Subject', $data['subject']);
                    $questionType = $data['questionType'];
                    if ($questionType > 0) { // Um tipo específico de questão foi selecionado
                        $questions = $em->getRepository('SchoolManagement\Entity\ExamQuestion')->findBy(array(
                            'examQuestionType' => $questionType,
                            'subject' => $subject,
                        ));
                    } else {
                        $questions = $em->getRepository('SchoolManagement\Entity\ExamQuestion')->findBy(array(
                            'subject' => $subject,
                        ));
                    }
                    foreach ($questions as $q) {
                        $answers = '';
                        $answerOptions = $q->getAnswerOptions()->toArray();
                        foreach ($answerOptions as $ao) {
                            $answers .= $ao->getExamAnswerDescription() . "<br>";
                        }
                        $result[] = array(
                            'questionId' => $q->getExamQuestionId(),
                            'questionEnunciation' => $q->getExamQuestionEnunciation(),
                            'questionAnswer' => $answers,
                        );
                    }
                }
            } catch (Exception $ex) {
                $result[] = array(
                    'questionId' => -1,
                    'questionEnunciation' => 'Erro: ' . $ex,
                    'questionAnswer' => '-',
                );
            }
        }
        return new JsonModel($result);
    }

    /**
     * Exibe em uma tabela todas as questões cadastradas
     * 
     * @return ViewModel
     */
    public function questionAction()
    {
        try {
            $em = $this->getEntityManager();
            $form = new SearchQuestionsForm($em);

            return new ViewModel(array(
                'message' => null,
                'form' => $form,
            ));
        } catch (Exception $ex) {
            return new ViewModel(array(
                'message' => 'Erro inesperado. Por favor entre em contato com o administrador do sistema.' .
                'Erro: ' . $ex->getMessage(),
                'form' => null,
            ));
        }
    }

    /**
     * Exibe um formulário de edição para a questão selecionada
     * 
     * @return ViewModel
     */
    public function editQuestionAction()
    {
        $em = $this->getEntityManager();
        $request = $this->getRequest();
        $message = null;

        $q = $this->params('id', false);
        if ($q) {
            try {
                $question = $em->find('SchoolManagement\Entity\ExamQuestion', $q);
                $aId = null;
                foreach ($question->getAnswerOptions() as $i => $q) {
                    if ($q->getIsCorrect()) {
                        $aId = $i;
                        break;
                    }
                }
                $typeBefore = $question->getExamQuestionType();
                $form = new AddExamQuestionForm($em, count($question->getAnswerOptions()->toArray()));
                $form->bind($question);
                $form->get('submit')->setAttribute('value', 'Editar');
                if ($request->isPost()) {
                    $form->setData($request->getPost());
                    if ($form->isValid()) {
                        $data = $form->getData(\Zend\Form\FormInterface::VALUES_AS_ARRAY)['exam-question'];

                        //  Conversão para inteiro
                        $ao = $question->getAnswerOptions()->toArray();
                        $correctAnswer = (int) ($data['correctAnswer']);
                        $examQuestionType = (int) ($data['examQuestionType']);
                        
                        $alternatives = count($ao);
                        if ($examQuestionType === ExamQuestion::QUESTION_TYPE_CLOSED &&
                            $correctAnswer >= 0 && $correctAnswer < $alternatives &&
                            $correctAnswer !== $aId) {
                            // Se a resposta correta mudou mas a antiga ainda existe ela é desmarcada (isCorrect = false)
                            if ($aId !== null && $aId < $alternatives) {
                                $ao[$aId]->setIsCorrect(false);
                            }
                            $ao[$correctAnswer]->setIsCorrect(true);
                        }

                        //  Se o tipo da questão foi editado de fechada para aberta, remove todas as alternativas
                        if ($typeBefore === ExamQuestion::QUESTION_TYPE_CLOSED &&
                            $examQuestionType === ExamQuestion::QUESTION_TYPE_OPEN) {
                            $question->removeAnswerOptions($question->getAnswerOptions());
                        }
                        $question->setSubject($em->find('SchoolManagement\Entity\Subject', $data['subjectId']));
                        $em->persist($question);
                        $em->flush();
                        $this->redirect()->toRoute('school-management/school-exam', array('action' => 'question'));
                    }
                }

                return new ViewModel(array(
                    'message' => $message,
                    'form' => $form,
                    'sId' => $question->getSubject()->getSubjectId(),
                    'aId' => $aId,
                ));
            } catch (Exception $ex) {
                $message = 'Erro inesperado. Entre com contato com o administrador do sistema.<br>' .
                    'Erro: ' . $ex->getMessage();
            }
        } else {
            $message = 'Nenhuma questão foi selecionda.';
        }
        return new ViewModel(array(
            'message' => $message,
            'form' => null,
            'sId' => null,
            'aId' => null,
        ));
    }

    /**
     * Remove do banco de dados a questão selecionada
     * 
     * @return JsonModel
     */
    public function deleteQuestionAction()
    {
        $message = null;
        $q = $this->params('id', false);
        if ($q) {
            try {
                $em = $this->getEntityManager();
                $question = $em->find('SchoolManagement\Entity\ExamQuestion', $q);
                $em->remove($question);
                $em->flush();
                $message = 'Questão removida com sucesso.';
                return new JsonModel(array(
                    'message' => $message,
                    'callback' => array(
                        'questionId' => $q,
                    ),
                ));
            } catch (Exception $ex) {
                $message = 'Erro inesperado. Entre com contato com o administrador do sistema.<br>' .
                    'Erro: ' . $ex->getMessage();
            }
        } else {
            $message = 'Nenhuma questão foi selecionda.';
        }
        return new JsonModel(array(
            'message' => $message,
        ));
    }

    /**
     * Exibe um formulário para adição de uma questão da disciplina selecionada ao banco de questões
     * 
     * @return ViewModel
     */
    public function addQuestionAction()
    {
        $em = $this->getEntityManager();
        $request = $this->getRequest();

        $form = new AddExamQuestionForm($em);
        $examQuestion = new ExamQuestion();
        $form->bind($examQuestion);
        if ($request->isPost()) {
            $form->setData($request->getPost());

            if ($form->isValid()) {
                $data = $form->getData(\Zend\Form\FormInterface::VALUES_AS_ARRAY)['exam-question'];
                $ao = $examQuestion->getAnswerOptions()->toArray();

                //  Conversão para inteiro
                $correctAnswer = (int) ($data['correctAnswer']);
                $examQuestionType = (int) ($data['examQuestionType']);
                $alternatives = count($data['answerOptions']);

                if ($examQuestionType === ExamQuestion::QUESTION_TYPE_CLOSED &&
                    $correctAnswer >= 0 && $correctAnswer < $alternatives) {
                    $ao[$correctAnswer]->setIsCorrect(true);
                }
                $examQuestion->setSubject($em->find('SchoolManagement\Entity\Subject', $data['subjectId']));
                $em->persist($examQuestion);
                $em->flush();

                // Se o procedimento for bem sucedido, a página é redirecionada para o banco de questões
                $this->redirect()->toRoute('school-management/school-exam', array('action' => 'question'));
            }
        }
        return new ViewModel(array(
            'message' => null,
            'form' => $form,
        ));
    }

}