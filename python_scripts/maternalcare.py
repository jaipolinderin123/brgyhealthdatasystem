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
class MaternalCareConfig:
    """Configuration for maternal care clustering analysis"""
    output_dir: str = 'maternal_care_clustering_output'
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'maternal_care'
    random_state: int = 42
    generate_sample: bool = False
    max_clusters: int = 6

class MaternalCareDataHandler:
    def __init__(self, config: MaternalCareConfig = MaternalCareConfig()):
        self.config = config
    
    def fetch_data(self) -> pd.DataFrame:
        """Fetch data from database or generate sample"""
        if self.config.generate_sample:
            logger.info("Generating sample maternal care data")
            return self._generate_sample_data()
            
        try:
            logger.info("Fetching data from database")
            engine = create_engine(self.config.db_connection)
            query = f"SELECT * FROM {self.config.table_name};"
            df = pd.read_sql(query, engine)
            
            if df.empty:
                logger.warning("Empty dataset from database, generating sample")
                return self._generate_sample_data()
                
            return self._clean_data(df)
            
        except Exception as e:
            logger.error(f"Database error: {str(e)}")
            logger.info("Falling back to sample data")
            return self._generate_sample_data()

    def _clean_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Clean and prepare the raw data"""
        # Replace string representations of missing values
        missing_values = ['none', 'None', 'null', 'NULL', '', 'NA', 'N/A']
        df = df.replace(missing_values, np.nan)
        
        # Convert date columns to datetime
        date_columns = [col for col in df.columns if 'date' in col.lower()]
        for col in date_columns:
            df[col] = pd.to_datetime(df[col], errors='coerce')
        
        # Convert numeric columns
        numeric_cols = [
            'age', 'bmi', 'birth_weight', 'iron_no_tablets_1st_tri',
            'iron_no_tablets_2nd_tri', 'iron_no_tablets_3rd_tri',
            'calcium_no_tablets_1st_tri', 'calcium_no_tablets_2nd_tri',
            'calcium_no_tablets_3rd_tri', 'purok'
        ]
        
        for col in numeric_cols:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce')
        
        # Convert binary/categorical columns
        binary_cols = [
            'given_iron', 'deworming_tablet_given', 'iodine_capsule_given',
            'completed_90_tablets_iron', 'vitamin_a_given'
        ]
        
        for col in binary_cols:
            if col in df.columns:
                df[col] = df[col].replace({'yes': 1, 'no': 0, 'Yes': 1, 'No': 0})
                df[col] = pd.to_numeric(df[col], errors='coerce')
                df[col] = df[col].fillna(0)  # Assume missing means not given
        
        # Create derived features
        if all(col in df.columns for col in ['pre_natal_date_1st_tri', 'pre_natal_date_2nd_tri', 'pre_natal_date_3rd_tri']):
            df['prenatal_visits'] = df[['pre_natal_date_1st_tri', 'pre_natal_date_2nd_tri', 
                                       'pre_natal_date_3rd_tri']].notna().sum(axis=1)
        
        if 'registration_date' in df.columns and 'date_of_delivery' in df.columns:
            df['pregnancy_duration_days'] = (df['date_of_delivery'] - df['registration_date']).dt.days
        
        # Drop columns with too many missing values
        df = df.dropna(axis=1, thresh=len(df)*0.5)
        
        return df

    def _generate_sample_data(self) -> pd.DataFrame:
        """Generate synthetic maternal care data"""
        np.random.seed(self.config.random_state)
        n_samples = 300
        
        data = {
            'age': np.random.randint(18, 45, n_samples),
            'bmi': np.round(np.random.uniform(18.5, 35.0, n_samples), 1),
            'purok': np.random.randint(1, 10, n_samples),
            'iron_no_tablets_1st_tri': np.random.randint(0, 60, n_samples),
            'iron_no_tablets_2nd_tri': np.random.randint(0, 60, n_samples),
            'given_iron': np.random.choice([0, 1], n_samples, p=[0.2, 0.8]),
            'birth_weight': np.round(np.random.uniform(2.5, 4.5, n_samples), 2),
            'prenatal_visits': np.random.poisson(5, n_samples) + 1,
            'deworming_tablet_given': np.random.choice([0, 1], n_samples, p=[0.3, 0.7]),
            'vitamin_a_given': np.random.choice([0, 1], n_samples, p=[0.4, 0.6]),
            'type_of_delivery': np.random.choice(['Normal', 'C-section', 'Assisted'], n_samples),
            'place_of_delivery': np.random.choice(['Hospital', 'Clinic', 'Home'], n_samples),
            'completed_90_tablets_iron': np.random.choice([0, 1], n_samples, p=[0.5, 0.5])
        }
        
        return pd.DataFrame(data)

class MaternalCareClusterer:
    def __init__(self, config: MaternalCareConfig = MaternalCareConfig()):
        self.config = config
        self.preprocessor: Optional[ColumnTransformer] = None
        self.model: Optional[KMeans] = None
        self.optimal_clusters: Optional[int] = None
        self.cluster_metrics: List[Dict] = []
        self.db_engine = create_engine(self.config.db_connection)
        os.makedirs(self.config.output_dir, exist_ok=True)

    def preprocess_data(self, df: pd.DataFrame) -> np.ndarray:
        """Feature selection and preprocessing for maternal care data"""
        # Define feature groups
        numeric_features = []
        categorical_features = []
        binary_features = []
        
        # Check and add available features
        if 'age' in df.columns:
            numeric_features.append('age')
        if 'bmi' in df.columns:
            numeric_features.append('bmi')
        if 'birth_weight' in df.columns:
            numeric_features.append('birth_weight')
        if 'prenatal_visits' in df.columns:
            numeric_features.append('prenatal_visits')
        if 'pregnancy_duration_days' in df.columns:
            numeric_features.append('pregnancy_duration_days')
        
        # Iron supplementation features
        iron_features = [col for col in df.columns if 'iron_no_tablets' in col]
        numeric_features.extend(iron_features)
        
        # Binary features
        binary_candidates = [
            'given_iron', 'deworming_tablet_given', 'vitamin_a_given',
            'completed_90_tablets_iron', 'iodine_capsule_given'
        ]
        binary_features = [col for col in binary_candidates if col in df.columns]
        
        # Categorical features
        categorical_candidates = [
            'type_of_delivery', 'place_of_delivery', 'birth_attendant'
        ]
        categorical_features = [col for col in categorical_candidates if col in df.columns]
        
        # Fill missing values
        for col in numeric_features:
            df[col] = df[col].fillna(df[col].median())
        for col in binary_features:
            df[col] = df[col].fillna(0)  # Assume missing means no/false
        
        # Create preprocessing pipeline
        transformers = []
        if numeric_features:
            transformers.append(('num', StandardScaler(), numeric_features))
        if categorical_features:
            transformers.append(('cat', OneHotEncoder(handle_unknown='ignore'), categorical_features))
        if binary_features:
            transformers.append(('binary', 'passthrough', binary_features))
        
        if not transformers:
            raise ValueError("No valid features available for clustering")
            
        self.preprocessor = ColumnTransformer(transformers=transformers)
        
        return self.preprocessor.fit_transform(df)

    def evaluate_cluster_options(self, X: np.ndarray) -> List[Dict]:
        """Evaluate different numbers of clusters and return metrics"""
        metrics = []
        for k in range(2, self.config.max_clusters + 1):
            try:
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
            except Exception as e:
                logger.error(f"Error clustering with k={k}: {str(e)}")
                continue
        
        # Calculate elbow differences
        for i in range(1, len(metrics)):
            metrics[i]['elbow_diff'] = metrics[i-1]['inertia'] - metrics[i]['inertia']
        
        return metrics
    
    def determine_optimal_clusters(self, metrics: List[Dict]) -> int:
        """Determine optimal clusters using combined silhouette and elbow method"""
        if not metrics:
            logger.warning("No valid clustering metrics available, using default k=3")
            return 3
            
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
            'maternal_care_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        metrics_file = os.path.join(self.config.output_dir, f"maternal_care_metrics_{timestamp}.csv")
        metrics_df.to_csv(metrics_file, index=False)
        
        # Save cluster assignments
        cluster_df = df.copy()
        try:
            cluster_df['cluster'] = self.get_cluster_labels(df)
            cluster_df.to_sql(
                'maternal_care_clustering_results',
                self.db_engine,
                if_exists='replace',
                index=False
            )
            cluster_file = os.path.join(self.config.output_dir, f"maternal_care_clusters_{timestamp}.csv")
            cluster_df.to_csv(cluster_file, index=False)
        except Exception as e:
            logger.error(f"Error saving cluster assignments: {str(e)}")
        
        logger.info(f"Results saved to database and {self.config.output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        try:
            X = self.preprocess_data(df)
            self.cluster_metrics = self.evaluate_cluster_options(X)
            
            if not self.cluster_metrics:
                raise ValueError("No valid clustering results obtained")
            
            logger.info("\nMaternal Care Cluster Evaluation Metrics:")
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
            
        except Exception as e:
            logger.error(f"Error in clustering: {str(e)}")
            raise
    
    def get_cluster_labels(self, df: pd.DataFrame) -> np.ndarray:
        """Get cluster assignments for data"""
        X = self.preprocess_data(df)
        return self.model.predict(X)
    
    def analyze_clusters(self, df: pd.DataFrame):
        """Analyze cluster characteristics"""
        try:
            df['cluster'] = self.get_cluster_labels(df)
            
            logger.info("\nMaternal Care Cluster Analysis:")
            logger.info("\n1. Cluster Distribution:")
            logger.info(df['cluster'].value_counts().sort_index())
            
            # Analyze numeric features
            numeric_cols = [col for col in df.columns if pd.api.types.is_numeric_dtype(df[col]) and col != 'cluster']
            if numeric_cols:
                logger.info("\n2. Numeric Features by Cluster:")
                logger.info(df.groupby('cluster')[numeric_cols].agg(['mean', 'std']))
            
            # Analyze categorical features
            categorical_cols = ['type_of_delivery', 'place_of_delivery', 'birth_attendant']
            categorical_cols = [col for col in categorical_cols if col in df.columns]
            
            for col in categorical_cols:
                logger.info(f"\n3. {col} Distribution by Cluster:")
                logger.info(pd.crosstab(df['cluster'], df[col], normalize='index').round(2))
            
            # Analyze iron supplementation
            if 'given_iron' in df.columns:
                logger.info("\n4. Iron Supplementation by Cluster:")
                logger.info(pd.crosstab(df['cluster'], df['given_iron'], normalize='index').round(2))
                
            if 'completed_90_tablets_iron' in df.columns:
                logger.info("\n5. Completed 90 Iron Tablets by Cluster:")
                logger.info(pd.crosstab(df['cluster'], df['completed_90_tablets_iron'], normalize='index').round(2))
            
            # Analyze purok distribution
            if 'purok' in df.columns:
                logger.info("\n6. Purok Distribution by Cluster:")
                logger.info(df.groupby('cluster')['purok'].value_counts(normalize=True).round(2))
                
        except Exception as e:
            logger.error(f"Error in cluster analysis: {str(e)}")

if __name__ == "__main__":
    config = MaternalCareConfig(
    db_connection='mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata',
    generate_sample=False  # Set to True for sample data
)

    try:
        data_handler = MaternalCareDataHandler(config)
        maternal_data = data_handler.fetch_data()
        
        clusterer = MaternalCareClusterer(config)
        clusterer.fit(maternal_data)
        clusterer.analyze_clusters(maternal_data)
    except Exception as e:
        logger.error(f"Application error: {str(e)}")