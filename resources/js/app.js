import './bootstrap';

import Alpine from 'alpinejs';
import borradorFormulario from './borrador-formulario';
import evidencias from './evidencias';
import firmaTactil, { lienzoFirma } from './firma-tactil';

window.Alpine = Alpine;

Alpine.data('firmaTactil', firmaTactil);
Alpine.data('lienzoFirma', lienzoFirma);
Alpine.data('evidencias', evidencias);
Alpine.data('borradorFormulario', borradorFormulario);

Alpine.start();
