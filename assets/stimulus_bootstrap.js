import { startStimulusApp } from '@symfony/stimulus-bundle';
import BurgerController from './controllers/burger_controller.js';
import ConfirmModalController from './controllers/confirm_modal_controller.js';
import ConfirmModalTriggerController from './controllers/confirm_modal_trigger_controller.js';
import CsrfProtectionController from './controllers/csrf_protection_controller.js';
import DropzoneController from './controllers/dropzone_controller.js';
import ModalsController from './controllers/modals_controller.js';

const app = startStimulusApp();
app.register('burger', BurgerController);
app.register('confirm-modal', ConfirmModalController);
app.register('confirm-modal-trigger', ConfirmModalTriggerController);
app.register('csrf-protection', CsrfProtectionController);
app.register('dropzone', DropzoneController);
app.register('modals', ModalsController);
