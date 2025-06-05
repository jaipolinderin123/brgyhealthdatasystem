import numpy as np
import pandas as pd
import sqlalchemy
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.metrics import silhouette_score
from dataclasses import dataclass
from typing import List, Dict
import os
from datetime import datetime

@dataclass
class BloodPressureDataGenerator:
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'blood_pressure_monitoring'
    
    def generate_data(self, n_samples=1000) -> pd.DataFrame:
        """Generate synthetic blood pressure monitoring data"""
        return pd.DataFrame({
            'date_of_consultation': pd.to_datetime('2023-01-01') + pd.to_timedelta(
                np.random.randint(0, 365, n_samples), unit='D'),
            'purok': np.random.randint(1, 8, n_samples),
            'gender': np.random.choice(['Male', 'Female'], n_samples, p=[0.48, 0.52]),
            'birthday': pd.to_datetime('1970-01-01') + pd.to_timedelta(
                np.random.randint(0, 365*70, n_samples), unit='D'),
            'age': np.random.randint(18, 90, n_samples),
            'blood_pressure': [f"{np.random.randint(90, 180)}/{np.random.randint(60, 100)}" for _ in range(n_samples)],
            'membership': np.random.choice(['Yes', 'No'], n_samples, p=[0.7, 0.3])
        })

class BloodPressureClusterer:
    def __init__(self, max_clusters: int = 6, random_state: int = 42):
        self.max_clusters = max_clusters
        self.random_state = random_state
        self.preprocessor = None
        self.model = None
        self.optimal_clusters = None
        self.cluster_metrics = []
        self.db_engine = sqlalchemy.create_engine(
            'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
        )

    def preprocess_data(self, df: pd.DataFrame) -> np.ndarray:
        """Feature selection and preprocessing for blood pressure data"""
        # Extract systolic and diastolic from blood_pressure
        df[['systolic', 'diastolic']] = df['blood_pressure'].str.split('/', expand=True).astype(int)
        
        features = df[['age', 'systolic', 'diastolic', 'gender', 'membership']]
        
        numeric_features = ['age', 'systolic', 'diastolic']
        categorical_features = ['gender', 'membership']
        
        self.preprocessor = ColumnTransformer(
            transformers=[
                ('num', StandardScaler(), numeric_features),
                ('cat', OneHotEncoder(), categorical_features)
            ])
        
        return self.preprocessor.fit_transform(features)
    
    def evaluate_cluster_options(self, X: np.ndarray) -> List[Dict]:
        """Evaluate different numbers of clusters and return metrics"""
        metrics = []
        for k in range(2, self.max_clusters + 1):
            kmeans = KMeans(n_clusters=k, random_state=self.random_state)
            labels = kmeans.fit_predict(X)
            inertia = kmeans.inertia_
            silhouette = silhouette_score(X, labels)
            
            metrics.append({
                'k': k,
                'inertia': inertia,
                'silhouette_score': silhouette,
                'elbow_diff': None  # Will be calculated later
            })
        
        # Calculate elbow differences
        for i in range(1, len(metrics)):
            metrics[i]['elbow_diff'] = metrics[i-1]['inertia'] - metrics[i]['inertia']
        
        return metrics
    
    def determine_optimal_clusters(self, metrics: List[Dict]) -> int:
        """Determine optimal clusters using combined silhouette and elbow method"""
        # Find best silhouette score
        best_silhouette = max(m['silhouette_score'] for m in metrics)
        silhouette_candidates = [m for m in metrics if m['silhouette_score'] >= best_silhouette * 0.95]
        
        # Among high silhouette candidates, find the elbow point
        if len(metrics) > 1:
            elbow_diffs = [m['elbow_diff'] for m in metrics[1:]]
            avg_elbow_diff = sum(elbow_diffs) / len(elbow_diffs)
            for m in reversed(silhouette_candidates):
                if m['elbow_diff'] > avg_elbow_diff:
                    return m['k']
        
        return silhouette_candidates[0]['k']
    
    def save_results(self, df: pd.DataFrame, metrics: List[Dict], optimal_k: int):
        """Save results to both database and local directory"""
        # Create output directory with timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_dir = f"bp_clustering_results_{timestamp}"
        os.makedirs(output_dir, exist_ok=True)
        
        # Prepare metrics data
        metrics_df = pd.DataFrame(metrics)
        metrics_df['optimal_k'] = (metrics_df['k'] == optimal_k).astype(int)
        
        # Save to database
        metrics_df.to_sql(
            'bp_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        metrics_file = os.path.join(output_dir, "bp_cluster_metrics.csv")
        metrics_df.to_csv(metrics_file, index=False)
        
        # Prepare cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        
        # Extract blood pressure components
        cluster_df[['systolic', 'diastolic']] = cluster_df['blood_pressure'].str.split('/', expand=True).astype(int)
        
        # Save to database
        cluster_df.to_sql(
            'bp_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        cluster_file = os.path.join(output_dir, "bp_cluster_assignments.csv")
        cluster_df.to_csv(cluster_file, index=False)
        
        print(f"\nResults saved to database and directory: {output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        # Display evaluation metrics
        print("Blood Pressure Cluster Evaluation Metrics:")
        print(f"{'K':<5}{'Inertia':<15}{'Silhouette':<15}{'Elbow Diff':<15}")
        print("-" * 50)
        for m in self.cluster_metrics:
            print(f"{m['k']:<5}{m['inertia']:<15.2f}{m['silhouette_score']:<15.4f}"
                  f"{str(m['elbow_diff'])[:7]:<15}")
        
        self.optimal_clusters = self.determine_optimal_clusters(self.cluster_metrics)
        print(f"\nAutomatically selected optimal clusters: {self.optimal_clusters}")
        
        self.model = KMeans(n_clusters=self.optimal_clusters, random_state=self.random_state)
        self.model.fit(X)
        
        # Save results
        self.save_results(df, self.cluster_metrics, self.optimal_clusters)
        return self
    
    def get_cluster_labels(self, df: pd.DataFrame) -> np.ndarray:
        """Get cluster assignments for data"""
        X = self.preprocess_data(df)
        return self.model.predict(X)
    
    def analyze_clusters(self, df: pd.DataFrame):
        """Analyze cluster characteristics"""
        df['cluster'] = self.get_cluster_labels(df)
        
        # Extract blood pressure components for analysis
        df[['systolic', 'diastolic']] = df['blood_pressure'].str.split('/', expand=True).astype(int)
        
        print("\nFinal Blood Pressure Cluster Analysis:")
        print("\n1. Cluster Distribution:")
        print(df['cluster'].value_counts().sort_index())
        
        print("\n2. Age and Blood Pressure Statistics by Cluster:")
        print(df.groupby('cluster')[['age', 'systolic', 'diastolic']].agg(['mean', 'std', 'min', 'max']))
        
        print("\n3. Purok Distribution by Cluster:")
        print(pd.crosstab(df['cluster'], df['purok'], normalize='index').round(2))
        
        print("\n4. Gender Distribution by Cluster:")
        print(pd.crosstab(df['cluster'], df['gender'], normalize='index').round(2))
        
        print("\n5. Membership Status by Cluster:")
        print(pd.crosstab(df['cluster'], df['membership'], normalize='index').round(2))

if __name__ == "__main__":
    # Generate synthetic data
    generator = BloodPressureDataGenerator()
    bp_data = generator.generate_data(1500)
    
    # Initialize and run cluster analysis
    clusterer = BloodPressureClusterer(max_clusters=6)
    clusterer.fit(bp_data)
    clusterer.analyze_clusters(bp_data)