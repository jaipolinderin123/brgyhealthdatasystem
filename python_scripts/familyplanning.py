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
class FamilyPlanningConfig:
    """Configuration for family planning clustering analysis"""
    output_dir: str = 'family_planning_clustering_output'
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'family_planning'
    random_state: int = 42
    generate_sample: bool = False
    max_clusters: int = 6

class FamilyPlanningDataHandler:
    def __init__(self, config: FamilyPlanningConfig = FamilyPlanningConfig()):
        self.config = config
    
    def fetch_data(self) -> pd.DataFrame:
        """Fetch data from database or generate sample"""
        if self.config.generate_sample:
            logger.info("Generating sample family planning data")
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
        """Generate realistic family planning sample data"""
        np.random.seed(self.config.random_state)
        n_samples = 200
        
        methods = ['Pills', 'Condoms', 'Injectable', 'IUD', 'Implant', 'Natural']
        se_statuses = ['Poor', 'Low Income', 'Middle Class', 'High Income']
        client_types = ['New', 'Repeater', 'Dropout']
        sources = ['Health Center', 'Private Clinic', 'Outreach', 'Referral']
        
        return pd.DataFrame({
            'date_of_registration': pd.date_range(start='2020-01-01', periods=n_samples, freq='D').date,
            'purok': np.random.randint(1, 15, n_samples),
            'age': np.random.randint(15, 50, n_samples),
            'birthday': [datetime.now().date().replace(year=datetime.now().year - age) 
                         for age in np.random.randint(15, 50, n_samples)],
            'se_status': np.random.choice(se_statuses, n_samples, p=[0.3, 0.4, 0.2, 0.1]),
            'type_of_client': np.random.choice(client_types, n_samples, p=[0.4, 0.5, 0.1]),
            'source': np.random.choice(sources, n_samples, p=[0.6, 0.2, 0.15, 0.05]),
            'previous_method': np.random.choice(methods + [None], n_samples, 
                                              p=[0.3, 0.2, 0.15, 0.1, 0.1, 0.05, 0.1])
        })

class FamilyPlanningClusterer:
    def __init__(self, config: FamilyPlanningConfig = FamilyPlanningConfig()):
        self.config = config
        self.preprocessor: Optional[ColumnTransformer] = None
        self.model: Optional[KMeans] = None
        self.optimal_clusters: Optional[int] = None
        self.cluster_metrics: List[Dict] = []
        self.db_engine = create_engine(self.config.db_connection)
        os.makedirs(self.config.output_dir, exist_ok=True)

    def preprocess_data(self, df: pd.DataFrame) -> np.ndarray:
        """Feature selection and preprocessing for family planning data"""
        features = df[['age', 'purok', 'se_status', 'type_of_client', 'source', 'previous_method']]
        
        numeric_features = ['age', 'purok']
        categorical_features = ['se_status', 'type_of_client', 'source', 'previous_method']
        
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
            'family_planning_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        metrics_df.to_csv(
            os.path.join(self.config.output_dir, f"family_planning_metrics_{timestamp}.csv"),
            index=False
        )
        
        # Save cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        cluster_df.to_sql(
            'family_planning_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        cluster_df.to_csv(
            os.path.join(self.config.output_dir, f"family_planning_clusters_{timestamp}.csv"),
            index=False
        )
        
        logger.info(f"Results saved to database and {self.config.output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        logger.info("\nFamily Planning Cluster Evaluation Metrics:")
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
        
        logger.info("\nFamily Planning Cluster Analysis:")
        logger.info("\n1. Cluster Distribution:")
        logger.info(df['cluster'].value_counts().sort_index())
        
        logger.info("\n2. Age and Purok by Cluster:")
        logger.info(df.groupby('cluster')[['age', 'purok']].agg(['mean', 'std', 'min', 'max']))
        
        logger.info("\n3. Socioeconomic Status by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['se_status'], normalize='index').round(2))
        
        logger.info("\n4. Client Types by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['type_of_client'], normalize='index').round(2))
        
        logger.info("\n5. Service Source by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['source'], normalize='index').round(2))
        
        logger.info("\n6. Previous Methods by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['previous_method'], normalize='index').round(2))

if __name__ == "__main__":
    config = FamilyPlanningConfig(
        db_connection='mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata',
        generate_sample=False  # Set to True for sample data
    )
    
    data_handler = FamilyPlanningDataHandler(config)
    fp_data = data_handler.fetch_data()
    
    clusterer = FamilyPlanningClusterer(config)
    clusterer.fit(fp_data)
    clusterer.analyze_clusters(fp_data)