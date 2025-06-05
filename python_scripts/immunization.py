import numpy as np
import pandas as pd
from sqlalchemy import create_engine
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.metrics import silhouette_score
from dataclasses import dataclass
from typing import List, Dict, Optional
import os
from datetime import datetime
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class ImmunizationConfig:
    """Configuration for immunization clustering analysis"""
    output_dir: str = 'immunization_clustering_output'
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'immunization_data'
    random_state: int = 42
    generate_sample: bool = False
    max_clusters: int = 6

class ImmunizationDataHandler:
    def __init__(self, config: ImmunizationConfig = ImmunizationConfig()):
        self.config = config
    
    def fetch_data(self) -> pd.DataFrame:
        """Fetch data from database or generate sample"""
        if self.config.generate_sample:
            logger.info("Generating sample immunization data")
            return self._generate_sample_data()
            
        try:
            logger.info("Fetching data from database")
            engine = create_engine(self.config.db_connection)
            query = f"SELECT * FROM {self.config.table_name};"
            df = pd.read_sql(query, engine)
            
            if df.empty:
                logger.warning("Empty dataset from database, generating sample")
                return self._generate_sample_data()
                
            return df
            
        except Exception as e:
            logger.error(f"Database error: {str(e)}")
            logger.info("Falling back to sample data")
            return self._generate_sample_data()

    def _generate_sample_data(self) -> pd.DataFrame:
        """Generate realistic immunization sample data"""
        np.random.seed(self.config.random_state)
        n_samples = 500
        
        vaccine_types = ['BCG', 'Hepatitis B', 'Polio', 'DPT', 'Measles']
        genders = ['Male', 'Female']
        locations = ['Urban', 'Rural']
        health_workers = ['Doctor', 'Nurse', 'Midwife']
        
        data = {
            'age_in_months': np.concatenate([
                np.random.normal(3, 1, int(n_samples*0.2)),   # Newborns
                np.random.normal(8, 2, int(n_samples*0.3)),    # Infants
                np.random.normal(24, 6, int(n_samples*0.3)),   # Toddlers
                np.random.normal(36, 12, int(n_samples*0.2))   # Preschoolers
            ]).clip(0, 60),
            'weight': np.concatenate([
                np.random.normal(5, 1, int(n_samples*0.2)),    # Newborns
                np.random.normal(8, 2, int(n_samples*0.3)),    # Infants
                np.random.normal(12, 3, int(n_samples*0.3)),   # Toddlers
                np.random.normal(15, 4, int(n_samples*0.2))    # Preschoolers
            ]).clip(2.5, 25),
            'vaccine_type': np.random.choice(vaccine_types, n_samples),
            'dose': np.random.randint(1, 4, n_samples),
            'gender': np.random.choice(genders, n_samples),
            'location': np.random.choice(locations, n_samples, p=[0.6, 0.4]),
            'health_worker': np.random.choice(health_workers, n_samples)
        }
        return pd.DataFrame(data)

class ImmunizationClusterer:
    def __init__(self, config: ImmunizationConfig = ImmunizationConfig()):
        self.config = config
        self.preprocessor: Optional[ColumnTransformer] = None
        self.model: Optional[KMeans] = None
        self.optimal_clusters: Optional[int] = None
        self.cluster_metrics: List[Dict] = []
        self.db_engine = create_engine(self.config.db_connection)
        os.makedirs(self.config.output_dir, exist_ok=True)

    def preprocess_data(self, df: pd.DataFrame) -> np.ndarray:
        """Feature selection and preprocessing for immunization data"""
        features = df[['age_in_months', 'weight', 'dose', 
                      'vaccine_type', 'gender', 'location', 'health_worker']]
        
        numeric_features = ['age_in_months', 'weight', 'dose']
        categorical_features = ['vaccine_type', 'gender', 'location', 'health_worker']
        
        self.preprocessor = ColumnTransformer(
            transformers=[
                ('num', StandardScaler(), numeric_features),
                ('cat', OneHotEncoder(handle_unknown='ignore'), categorical_features)
            ])
        
        return self.preprocessor.fit_transform(features)
    
    def evaluate_cluster_options(self, X: np.ndarray) -> List[Dict]:
        """Evaluate different numbers of clusters and return metrics"""
        metrics = []
        for k in range(2, self.config.max_clusters + 1):
            kmeans = KMeans(n_clusters=k, random_state=self.config.random_state)
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
        
        # Calculate average elbow difference excluding None and first k
        elbow_diffs = [m['elbow_diff'] for m in metrics[1:] if m['elbow_diff'] is not None]
        
        if not elbow_diffs:  # If all elbow_diffs are None
            return silhouette_candidates[0]['k']
        
        avg_elbow_diff = sum(elbow_diffs) / len(elbow_diffs)
        
        # Among high silhouette candidates, find the elbow point
        for m in reversed(silhouette_candidates):
            if m['elbow_diff'] is not None and m['elbow_diff'] > avg_elbow_diff:
                return m['k']
        
        return silhouette_candidates[0]['k']
    
    def save_results(self, df: pd.DataFrame, metrics: List[Dict], optimal_k: int):
        """Save results to both database and local directory"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Save cluster metrics
        metrics_df = pd.DataFrame(metrics)
        metrics_df['optimal_k'] = (metrics_df['k'] == optimal_k).astype(int)
        metrics_df.to_sql(
            'immunization_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        metrics_df.to_csv(
            os.path.join(self.config.output_dir, f"immunization_metrics_{timestamp}.csv"),
            index=False
        )
        
        # Save cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        cluster_df.to_sql(
            'immunization_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        cluster_df.to_csv(
            os.path.join(self.config.output_dir, f"immunization_clusters_{timestamp}.csv"),
            index=False
        )
        
        logger.info(f"Results saved to database and {self.config.output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        logger.info("\nImmunization Cluster Evaluation Metrics:")
        logger.info(f"{'K':<5}{'Inertia':<15}{'Silhouette':<15}{'Elbow Diff':<15}")
        logger.info("-" * 50)
        for m in self.cluster_metrics:
            logger.info(f"{m['k']:<5}{m['inertia']:<15.2f}{m['silhouette_score']:<15.4f}"
                        f"{str(m['elbow_diff'])[:7]:<15}")
        
        self.optimal_clusters = self.determine_optimal_clusters(self.cluster_metrics)
        logger.info(f"\nOptimal clusters selected: {self.optimal_clusters}")
        
        self.model = KMeans(n_clusters=self.optimal_clusters, random_state=self.config.random_state)
        self.model.fit(X)
        
        self.save_results(df, self.cluster_metrics, self.optimal_clusters)
        return self
    
    def get_cluster_labels(self, df: pd.DataFrame) -> np.ndarray:
        """Get cluster assignments for data"""
        X = self.preprocess_data(df)
        return self.model.predict(X)
    
    def analyze_clusters(self, df: pd.DataFrame):
        """Analyze cluster characteristics"""
        df['cluster'] = self.get_cluster_labels(df)
        
        logger.info("\nImmunization Cluster Analysis:")
        logger.info("\n1. Cluster Distribution:")
        logger.info(df['cluster'].value_counts().sort_index())
        
        logger.info("\n2. Numeric Features by Cluster:")
        logger.info(df.groupby('cluster')[['age_in_months', 'weight', 'dose']].agg(['mean', 'std', 'min', 'max']))
        
        logger.info("\n3. Vaccine Types by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['vaccine_type'], normalize='index').round(2))
        
        logger.info("\n4. Location Distribution by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['location'], normalize='index').round(2))
        
        logger.info("\n5. Health Worker by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['health_worker'], normalize='index').round(2))

if __name__ == "__main__":
    config = ImmunizationConfig(
        db_connection='mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata',
        generate_sample=False  # Set to True for sample data
    )
    
    data_handler = ImmunizationDataHandler(config)
    immunization_data = data_handler.fetch_data()
    
    clusterer = ImmunizationClusterer(config)
    clusterer.fit(immunization_data)
    clusterer.analyze_clusters(immunization_data)