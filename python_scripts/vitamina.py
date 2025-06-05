import numpy as np
import pandas as pd
import sqlalchemy
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.metrics import silhouette_score
from dataclasses import dataclass
from typing import List, Dict

@dataclass
class HealthDataGenerator:
    db_connection:str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    def generate_data(self, n_samples=1000) -> pd.DataFrame:
        """Generate synthetic health data for clustering"""
        return pd.DataFrame({
            'purok': np.random.randint(1, 10, n_samples),
            'birthday': pd.to_datetime('2020-01-01') - pd.to_timedelta(
                np.random.randint(0, 365*5, n_samples), unit='D'),
            'age_in_months': np.random.randint(6, 60, n_samples),
            'gender': np.random.choice(['Male', 'Female'], n_samples, p=[0.52, 0.48]),
            'age_range_6_11_mons': np.where(np.random.randint(0, 2, n_samples), 'yes', 'no'),
            'age_range_12_59_mons': np.where(np.random.randint(0, 2, n_samples), 'yes', 'no')
        })

class HealthDataClusterer:
    def __init__(self, max_clusters: int = 10, random_state: int = 42):
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
        """Feature selection and preprocessing"""
        features = df[['age_in_months', 'gender', 'age_range_6_11_mons', 'age_range_12_59_mons']]
        
        numeric_features = ['age_in_months']
        categorical_features = ['gender', 'age_range_6_11_mons', 'age_range_12_59_mons']
        
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
    
    def save_results_to_db(self, df: pd.DataFrame, metrics: List[Dict], optimal_k: int):
        """Save all clustering results to database"""
        # Save cluster metrics
        metrics_df = pd.DataFrame(metrics)
        metrics_df['optimal_k'] = (metrics_df['k'] == optimal_k).astype(int)
        metrics_df.to_sql(
            'advanced_vitamin_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        cluster_df.to_sql(
            'advanced_vitamin_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        print(f"\nSaved results to database: advanced_vitamin_clustering_metrics and advanced_vitamin_clustering_results")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        # Display evaluation metrics
        print("Cluster Evaluation Metrics:")
        print(f"{'K':<5}{'Inertia':<15}{'Silhouette':<15}{'Elbow Diff':<15}")
        print("-" * 50)
        for m in self.cluster_metrics:
            print(f"{m['k']:<5}{m['inertia']:<15.2f}{m['silhouette_score']:<15.4f}"
                  f"{str(m['elbow_diff'])[:7]:<15}")
        
        self.optimal_clusters = self.determine_optimal_clusters(self.cluster_metrics)
        print(f"\nAutomatically selected optimal clusters: {self.optimal_clusters}")
        
        self.model = KMeans(n_clusters=self.optimal_clusters, random_state=self.random_state)
        self.model.fit(X)
        
        # Save results to database
        self.save_results_to_db(df, self.cluster_metrics, self.optimal_clusters)
        return self
    
    def get_cluster_labels(self, df: pd.DataFrame) -> np.ndarray:
        """Get cluster assignments for data"""
        X = self.preprocess_data(df)
        return self.model.predict(X)
    
    def analyze_clusters(self, df: pd.DataFrame):
        """Analyze cluster characteristics"""
        df['cluster'] = self.get_cluster_labels(df)
        
        print("\nFinal Cluster Analysis:")
        print("\n1. Cluster Distribution:")
        print(df['cluster'].value_counts().sort_index())
        
        print("\n2. Numeric Features by Cluster:")
        print(df.groupby('cluster')['age_in_months'].agg(['mean', 'std', 'count']))
        
        print("\n3. Categorical Features Distribution:")
        for feature in ['gender', 'age_range_6_11_mons', 'age_range_12_59_mons']:
            print(f"\n{feature}:")
            print(pd.crosstab(df['cluster'], df[feature], normalize='index').round(2))

if __name__ == "__main__":
    # Generate synthetic data
    generator = HealthDataGenerator()
    health_data = generator.generate_data(1000)
    
    # Initialize and run cluster analysis
    clusterer = HealthDataClusterer(max_clusters=8)
    clusterer.fit(health_data)
    clusterer.analyze_clusters(health_data)