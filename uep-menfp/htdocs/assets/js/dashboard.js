/**
 * Graphiques du tableau de bord.
 * Les données sont lues dans un bloc JSON déposé par la vue : aucun script
 * n'interpole de valeur PHP, la politique de sécurité reste stricte.
 */
(function () {
    'use strict';

    function demarrer() {
        var bloc = document.getElementById('donnees-graphiques');
        if (!bloc || typeof window.Chart === 'undefined') { return; }

        var donnees;
        try {
            donnees = JSON.parse(bloc.textContent);
        } catch (erreur) {
            return;
        }

        var police = "'Roboto', system-ui, sans-serif";
        window.Chart.defaults.font.family = police;
        window.Chart.defaults.font.size = 12;
        window.Chart.defaults.color = '#64748B';
        window.Chart.defaults.plugins.legend.labels.usePointStyle = true;
        window.Chart.defaults.maintainAspectRatio = false;

        var canevasRequisitions = document.getElementById('graphiqueRequisitions');
        if (canevasRequisitions) {
            new window.Chart(canevasRequisitions, {
                type: 'doughnut',
                data: {
                    labels: donnees.requisitions.labels,
                    datasets: [{
                        data: donnees.requisitions.valeurs,
                        backgroundColor: donnees.requisitions.couleurs,
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    cutout: '62%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        var canevasDossiers = document.getElementById('graphiqueDossiers');
        if (canevasDossiers) {
            new window.Chart(canevasDossiers, {
                type: 'bar',
                data: {
                    labels: donnees.dossiers.labels,
                    datasets: [
                        { label: 'UPD', data: donnees.dossiers.upd, backgroundColor: '#12406F', borderRadius: 4 },
                        { label: 'DDE', data: donnees.dossiers.dde, backgroundColor: '#C8102E', borderRadius: 4 }
                    ]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E2E8F0' } }
                    }
                }
            });
        }

        var canevasDepartements = document.getElementById('graphiqueDepartements');
        if (canevasDepartements) {
            new window.Chart(canevasDepartements, {
                type: 'bar',
                data: {
                    labels: donnees.departements.labels,
                    datasets: [
                        { label: 'UPD', data: donnees.departements.upd, backgroundColor: '#12406F', borderRadius: 4 },
                        { label: 'DDE', data: donnees.departements.dde, backgroundColor: '#C8102E', borderRadius: 4 }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E2E8F0' } },
                        y: { grid: { display: false } }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
}());
