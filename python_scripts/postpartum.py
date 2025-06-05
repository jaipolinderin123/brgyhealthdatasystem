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
class PostpartumConfig:
    random_state: int = 42
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'postpartum'

class PostpartumDataGenerator:
    def __init__(self, config: PostpartumConfig = PostpartumConfig()):
        self.config = config
    
    def _generate_sample_data(self) -> pd.DataFrame:
        """Generate sample dataset for postpartum with available features"""
        np.random.seed(self.config.random_state)
        n_samples = 200
        
        data = {
            'month': np.random.choice(['January', 'February', 'March', 'April', 'May', 'June',
                                     'July', 'August', 'September', 'October', 'November', 'December'], n_samples),
            'age_of_mother': np.random.normal(28, 5, n_samples).astype(int),
            'type_of_delivery': np.random.choice(['Normal', 'C-section'], n_samples, p=[0.7, 0.3]),
            'place_of_delivery': np.random.choice(['Hospital', 'Clinic', 'Home'], n_samples, p=[0.6, 0.3, 0.1]),
            'baby_weight': np.random.normal(3.2, 0.5, n_samples),  # kg
            'gender': np.random.choice(['Male', 'Female'], n_samples),
            'medical_staff_attend': np.random.choice(['Doctor', 'Nurse', 'Midwife'], n_samples),
            'date_of_delivery': pd.date_range(start='2020-01-01', periods=n_samples).date,
            'time_of_delivery': pd.to_datetime(np.random.randint(0, 86400, n_samples), unit='s').time
        }
        return pd.DataFrame(data)

class PostpartumClusterer:
    def __init__(self, max_clusters: int = 5, random_state: int = 42):
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
        """Feature selection and preprocessing for postpartum data"""
        # Select relevant features
        features = df[['age_of_mother', 'baby_weight', 'type_of_delivery', 
                       'place_of_delivery', 'medical_staff_attend']]
        
        # Define feature types
        numeric_features = ['age_of_mother', 'baby_weight']
        categorical_features = ['type_of_delivery', 'place_of_delivery', 'medical_staff_attend']
        
        # Create preprocessing pipeline
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
        output_dir = f"postpartum_clustering_results_{timestamp}"
        os.makedirs(output_dir, exist_ok=True)
        
        # Prepare metrics data
        metrics_df = pd.DataFrame(metrics)
        metrics_df['optimal_k'] = (metrics_df['k'] == optimal_k).astype(int)
        
        # Save to database
        metrics_df.to_sql(
            'postpartum_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        metrics_file = os.path.join(output_dir, "postpartum_cluster_metrics.csv")
        metrics_df.to_csv(metrics_file, index=False)
        
        # Prepare cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        
        # Save to database
        cluster_df.to_sql(
            'postpartum_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        cluster_file = os.path.join(output_dir, "postpartum_cluster_assignments.csv")
        cluster_df.to_csv(cluster_file, index=False)
        
        print(f"\nResults saved to database and directory: {output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        # Display evaluation metrics
        print("Postpartum Cluster Evaluation Metrics:")
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
        
        print("\nFinal Postpartum Cluster Analysis:")
        print("\n1. Cluster Distribution:")
        print(df['cluster'].value_counts().sort_index())
        
        print("\n2. Numeric Features by Cluster:")
        print(df.groupby('cluster')[['age_of_mother', 'baby_weight']].agg(['mean', 'std', 'min', 'max']))
        
        print("\n3. Delivery Type by Cluster:")
        print(pd.crosstab(df['cluster'], df['type_of_delivery'], normalize='index').round(2))
        
        print("\n4. Place of Delivery by Cluster:")
        print(pd.crosstab(df['cluster'], df['place_of_delivery'], normalize='index').round(2))
        
        print("\n5. Medical Staff by Cluster:")
        print(pd.crosstab(df['cluster'], df['medical_staff_attend'], normalize='index').round(2))
        
        print("\n6. Month Distribution by Cluster:")
        print(pd.crosstab(df['cluster'], df['month'], normalize='index').round(2))

if __name__ == "__main__":
    # Generate synthetic data
    config = PostpartumConfig()
    generator = PostpartumDataGenerator(config)
    postpartum_data = generator._generate_sample_data()
    
    # Initialize and run cluster analysis
    clusterer = PostpartumClusterer(max_clusters=5)
    clusterer.fit(postpartum_data)
    clusterer.analyze_clusters(postpartum_data)