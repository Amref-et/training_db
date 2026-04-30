import * as bootstrap from 'bootstrap';
import TomSelect from 'tom-select';
import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PieController,
    PointElement,
    RadarController,
    RadialLinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PieController,
    PointElement,
    RadarController,
    RadialLinearScale,
    Tooltip,
);

window.bootstrap = bootstrap;
window.TomSelect = TomSelect;
window.Chart = Chart;
