<?php
/**
 * MessageController for feedback module
 *
 * @category   Application
 * @package    Dashboard
 * @subpackage Controller
 *
 * @version  $Id: MessageController.php 1564 2009-10-30 09:09:03Z secunda $
 */
class Feedback_ManagementController extends Core_Controller_Action_Crud
{
    public function init()
    {
       /* Initialize */
        parent::init();

        $this->_beforeGridFilter(array(
             '_addAllTableColumns',
             '_addReadColumn',
             '_addDeleteColumn',
             '_showFilter'
        ));

        $this->_helper->getHelper('AjaxContext')
                      ->addActionContext('get-mail-template', 'html')
                      ->initContext();
    }

    /**
     * Read action
     */
    public function readAction()
    {
        $request = $this->getRequest();
        $id = intval($request->getParam('id', 0));
        if ($request->isPost() && $id) {
            $table = new Feedback_Model_Feedback_Table();
            $model = $table->getById($id);
            if ($model) {
                // Настроить форму чтения сообщения
                $form = new Feedback_Model_Feedback_Form_Reply();
                $form->setAction($this->view->url(array('action'=>'reply')));

                // get template for reply
                $mail = new Mail_Model_Templates_Table();
                $template = $mail->getModel('reply');

                $replyMail = $template->toArray();
                $replyMail['id'] = $model->id;
                $replyMail['sender'] = $model->sender;
                $replyMail['email'] = $model->email;
                $replyMail['message'] = $replyMail['bodyHtml'];
                unset($replyMail['body']);

                $form->setDefaults($replyMail);

                $this->view->form = $form;
                $this->view->mail = $model;
                // change feedback status
                $model->setFromArray(
                    array(
                        'status' => Feedback_Model_Feedback::STATUS_READ,
                        'updated' => date('Y-m-d H:i:s')
                    )
                );
                $model->save();
            } else {
                $this->_helper->getHelper('redirector')->direct('index');
            }
        } else {
            $this->_helper->getHelper('redirector')->direct('index');
        }
    }

    /**
     * Reply action
     */
    public function replyAction()
    {
        $this->_setDefaultScriptPath();
        $request = $this->getRequest();
        $id = intval($request->getParam('id', 0));

        if ($request->isPost() && $id) {
            $form = new Feedback_Model_Feedback_Form_Reply();
            $table = new Feedback_Model_Feedback_Table();
            $model = $table->getById($id);

            $form->setAction($this->view->url());

            // Проверить, если данные пришли из indexAction
            if ($request->getParam('viewForm', 0)) {
                $form->setDefault('id', $id);
                $form->setDefaults($model->toArray());
            } else {
                if ($model && $form->isValid($request->getPost())) {
                    $data = $form->getValues();
                    $message = $model->toArray();

                    $mail = new Zend_Mail();
                    // Формирование MIME данных
                    $mime = null;
                    if ($form->inputFile->isUploaded()) {
                        $file = $form->inputFile->getFileInfo();
                        $mime = Mail_Model_Mail::getMimePart(
                            array(
                                'file' => $file['inputFile']['tmp_name'],
                                'name' => $file['inputFile']['name'],
                                'description' => 'Attachment Image'
                            )
                        );
                        $mail->setMime($mime);
                        // Удалить загруженный файл
                        unlink($file['tmp_name']);
                    }
                    // Формирование шаблона собщения
                    $subject = $data['subject'] ?
                        $data['subject'] :
                        ('To reply on "' . $message['subject'] . '"');

                    $template = new Mail_Model_Templates_Model();
                    $template->toName    = $data['sender'];
                    $template->toEmail   = $data['email'];
                    $template->fromName  = $data['fromName'];
                    $template->fromEmail = $data['fromEmail'];
                    $template->subject   = $subject;
                    $template->bodyHtml  = $data['message'];

                    // if message with file, change %image% in template to link
                    if ($form->inputFile->isUploaded()) {
                        $image = '<img src="cid:' .
                                 $mime->id .
                                 '" title="' .
                                 $mime->description . '"/>';

                        $template->assign('image', $image);
                    }
                    // Посылка собщения
                    $template->send($mail);
                    // Если надо сохранить копию сообщения
                    if ($data['saveCopy']) {
                        $login = Zend_Auth::getInstance()->getIdentity()->login;
                        $model->setFromArray(
                            array(
                                'sender'  => ($data['sender'] ? $data['sender'] : $login),
                                'subject' => $subject,
                                'message' => $data['message'],
                                'email'   => ($data['email'] ? $data['email'] : 'zfc@nixsolutions.com'),
                                'status'  => Feedback_Model_Feedback::STATUS_REPLY,
                                'created' => date('Y-m-d H:i:s')
                            )
                        )->save();
                    }

                    $model->setFromArray(
                        array(
                            'status' => Feedback_Model_Feedback::STATUS_REPLY,
                            'updated' => date('Y-m-d H:i:s')
                        )
                    );
                    $model->save();

                    $this->_flashMessenger->addMessage('Successfully!');
                    $this->_helper->getHelper('redirector')->direct('index');
                }
            }
            $this->view->form = $form;
        } else {
            $this->_helper->getHelper('redirector')->direct('index');
        }
    }

    /**
     * getMailTemplate action
     */
    public function getMailTemplateAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            //$this->view->template = 'go'; return;
            $description = Zend_Filter_StripTags::filter(
                $request->getParam('description', 0)
            );
            if ($description) {
                $table = new Mail_Model_Templates_Table();
                $template = $table->getByDescription($description);
                $this->view->template = $template->bodyHtml;
            }
        }
    }

    /**
     * _getTable
     *
     * return model for scaffolding
     *
     * @return  Core_Model_Abstract
     */
    public function _getTable()
    {
        return new Feedback_Model_Feedback_Table();
    }

    /**
     * _getCreateForm
     *
     * return create form for scaffolding
     *
     * @return  Zend_Dojo_Form
     */
    public function _getCreateForm()
    {

    }

    /**
     * _getEditForm
     *
     * return edit form for scaffolding
     *
     * @return  Zend_Dojo_Form
     */
    public function _getEditForm()
    {

    }

    /**
    * add edit column to grid
    *
    * @return void
    */
    public function _addReadColumn()
    {
        $this->grid->setColumn('read', array(
            'name' => 'Read',
            'formatter' => array($this, 'readLinkFormatter')
        ));
    }

    /**
     * edit link formatter
     *
     * @param $value
     * @param $row
     * @return string
     */
    public function readLinkFormatter($value, $row)
    {
        $link = '<a href="%s" class="read">Read</a>';
        $url = $this->getHelper('url')->url(array(
            'action' => 'read',
            'id' => $row['id']
        ), 'default');

        return sprintf($link, $url);
    }


}
