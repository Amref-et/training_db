@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h1 class="text-2xl font-bold mb-6">Training Management Dashboard</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <h3 class="font-bold text-lg">Total Participants</h3>
                        <p class="text-2xl" id="total-participants">Loading...</p>
                    </div>
                    <div class="bg-green-100 p-4 rounded-lg">
                        <h3 class="font-bold text-lg">Total Trainings</h3>
                        <p class="text-2xl" id="total-trainings">Loading...</p>
                    </div>
                    <div class="bg-yellow-100 p-4 rounded-lg">
                        <h3 class="font-bold text-lg">Ongoing Trainings</h3>
                        <p class="text-2xl" id="ongoing-trainings">Loading...</p>
                    </div>
                    <div class="bg-purple-100 p-4 rounded-lg">
                        <h3 class="font-bold text-lg">Completed Trainings</h3>
                        <p class="text-2xl" id="completed-trainings">Loading...</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="font-bold text-lg mb-4">Participants by Region</h3>
                        <canvas id="participantsByRegionChart"></canvas>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="font-bold text-lg mb-4">Participants by Gender</h3>
                        <canvas id="participantsByGenderChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch dashboard data
        fetch('/api/dashboard')
            .then(response => response.json())
            .then(data => {
                // Update summary cards
                document.getElementById('total-participants').textContent = data.total_participants;
                document.getElementById('total-trainings').textContent = data.total_trainings;
                document.getElementById('ongoing-trainings').textContent = data.training_status.Ongoing || 0;
                document.getElementById('completed-trainings').textContent = data.training_status.Completed || 0;

                // Participants by Region Chart
                const regionCtx = document.getElementById('participantsByRegionChart').getContext('2d');
                new Chart(regionCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(data.participants_by_region),
                        datasets: [{
                            label: 'Participants',
                            data: Object.values(data.participants_by_region),
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Participants by Gender Chart
                const genderCtx = document.getElementById('participantsByGenderChart').getContext('2d');
                new Chart(genderCtx, {
                    type: 'pie',
                    data: {
                        labels: Object.keys(data.participants_by_gender),
                        datasets: [{
                            data: Object.values(data.participants_by_gender),
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            });
    });
</script>
@endsection