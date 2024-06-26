<?php
/**
 ***********************************************************************************************
 * Enter firstname and surname and checks if member already exists
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // only legitimate users are allowed to call the user management
    if (!$gCurrentUser->editUsers()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    echo '
    <script type="text/javascript">
        $("body").on("shown.bs.modal", ".modal", function() {
            $("#form_members_create_user:first *:input[type!=hidden]:first").focus();
        });

        $("#form_members_create_user").submit(function(event) {
            const action = $(this).attr("action");
            const formMembersAlert = $("#form_members_create_user .form-alert");
            formMembersAlert.hide();

            // disable default form submit
            event.preventDefault();

            $.post({
                url: action,
                data: $(this).serialize(),
                success: function(data) {
                    if (data === "success") {
                        formMembersAlert.attr("class", "alert alert-success form-alert");
                        formMembersAlert.html("<i class=\"bi bi-check-lg\"></i><strong>' . $gL10n->get('SYS_USER_COULD_BE_CREATED') . '</strong>");
                        formMembersAlert.fadeIn("slow");
                        setTimeout(function() {
                            self.location.href = "' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php?lastname=" + $("#lastname").val() + "&firstname=" + $("#firstname").val();
                        }, 2500);
                    } else {
                        if (data.length > 1000) {
                            $(".modal-body").html(data);
                        } else {
                            formMembersAlert.attr("class", "alert alert-danger form-alert");
                            formMembersAlert.fadeIn();
                            formMembersAlert.html("<i class=\"bi bi-exclamation-circle-fill\"></i>" + data);
                        }
                    }
                }
            });
        });
    </script>

    <div class="modal-header">
        <h3 class="modal-title">' . $gL10n->get('SYS_CREATE_CONTACT') . '</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <p class="lead">' . $gL10n->get('SYS_INPUT_FIRSTNAME_LASTNAME') . '</p>';
        $form = new HtmlForm('form_members_create_user', ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_assign.php', null, array('showRequiredFields' => false));
        $form->addInput(
            'lastname',
            $gL10n->get('SYS_LASTNAME'),
            '',
            array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addInput(
            'firstname',
            $gL10n->get('SYS_FIRSTNAME'),
            '',
            array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'btn_add',
            $gL10n->get('SYS_CREATE_CONTACT'),
            array('icon' => 'bi-plus-circle-fill')
        );
        echo $form->show();
    echo '</div>';
} catch (AdmException|Exception|\Smarty\Exception $e) {
    $gMessage->show($e->getMessage());
}
